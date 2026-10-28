<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Jobs\ReplicateBookFile;
use App\Models\Book;
use App\Models\BookFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BookFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('books_s3');
        Storage::fake('books_webdav');
        Sanctum::actingAs(User::factory()->editor()->create(), ['catalog:write']);
    }

    #[Test]
    public function an_upload_lands_on_the_default_backend_and_is_replicated(): void
    {
        $book = Book::factory()->create();

        $response = $this->post("/api/v1/books/{$book->slug}/files", ['file' => $this->pdf()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'extrait.pdf');

        $file = BookFile::query()->sole();
        Storage::disk('books_s3')->assertExists($file->path);
        // The queue runs synchronously in tests: the replica is already verified.
        Storage::disk('books_webdav')->assertExists($file->path);
        $this->assertSame(['webdav'], $file->replicas);
        $this->assertSame(hash('sha256', $this->pdfContent()), $file->checksum);
        $this->assertSame(['s3'], $response->json('data.stored_on'));
    }

    #[Test]
    public function the_backend_can_be_chosen_per_upload(): void
    {
        Queue::fake();
        $book = Book::factory()->create();

        $this->post("/api/v1/books/{$book->slug}/files", ['file' => $this->pdf(), 'backend' => 'webdav'], ['Accept' => 'application/json'])
            ->assertCreated();

        $file = BookFile::query()->sole();
        $this->assertSame('webdav', $file->backend);
        Storage::disk('books_webdav')->assertExists($file->path);
        Storage::disk('books_s3')->assertMissing($file->path);
        // Replication targets the configured replica only, never the primary itself.
        Queue::assertNothingPushed();
    }

    #[Test]
    public function an_unknown_backend_is_a_validation_error(): void
    {
        $book = Book::factory()->create();

        $this->post("/api/v1/books/{$book->slug}/files", ['file' => $this->pdf(), 'backend' => 'ftp'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('backend');
    }

    #[Test]
    public function the_same_content_is_stored_once(): void
    {
        Queue::fake();
        $book = Book::factory()->create();

        $this->post("/api/v1/books/{$book->slug}/files", ['file' => $this->pdf()], ['Accept' => 'application/json'])->assertCreated();
        $this->post("/api/v1/books/{$book->slug}/files", ['file' => $this->pdf()], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, BookFile::query()->count());
        Queue::assertPushed(ReplicateBookFile::class, 1);
    }

    #[Test]
    public function downloads_fall_back_to_the_replica_when_the_primary_copy_is_lost(): void
    {
        $book = Book::factory()->create();
        $this->post("/api/v1/books/{$book->slug}/files", ['file' => $this->pdf()], ['Accept' => 'application/json']);
        $file = BookFile::query()->sole();

        $this->get("/api/v1/files/{$file->id}/download")
            ->assertOk()
            ->assertHeader('X-Served-From', 's3')
            ->assertStreamedContent($this->pdfContent());

        Storage::disk('books_s3')->delete($file->path);

        $this->get("/api/v1/files/{$file->id}/download")
            ->assertOk()
            ->assertHeader('X-Served-From', 'webdav')
            ->assertDownload('extrait.pdf');
    }

    #[Test]
    public function a_file_lost_everywhere_answers_503(): void
    {
        Queue::fake();
        $book = Book::factory()->create();
        $this->post("/api/v1/books/{$book->slug}/files", ['file' => $this->pdf()], ['Accept' => 'application/json']);
        $file = BookFile::query()->sole();
        Storage::disk('books_s3')->delete($file->path);

        $this->getJson("/api/v1/files/{$file->id}/download")->assertServiceUnavailable();
    }

    #[Test]
    public function deleting_a_file_removes_every_copy(): void
    {
        $book = Book::factory()->create();
        $this->post("/api/v1/books/{$book->slug}/files", ['file' => $this->pdf()], ['Accept' => 'application/json']);
        $file = BookFile::query()->sole();

        $this->deleteJson("/api/v1/files/{$file->id}")->assertNoContent();

        $this->assertModelMissing($file);
        Storage::disk('books_s3')->assertMissing($file->path);
        Storage::disk('books_webdav')->assertMissing($file->path);
    }

    #[Test]
    public function a_corrupted_replica_is_not_recorded(): void
    {
        Queue::fake();
        $book = Book::factory()->create();
        $this->post("/api/v1/books/{$book->slug}/files", ['file' => $this->pdf()], ['Accept' => 'application/json']);
        $file = BookFile::query()->sole();
        Storage::disk('books_s3')->put($file->path, 'tampered');

        try {
            app()->call([new ReplicateBookFile($file->id, 'webdav'), 'handle']);
            $this->fail('A checksum mismatch must make the job fail (and be retried).');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('checksum', $e->getMessage());
        }

        $this->assertSame([], $file->fresh()?->replicas);
    }

    private function pdfContent(): string
    {
        return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('extrait.pdf', $this->pdfContent());
    }
}
