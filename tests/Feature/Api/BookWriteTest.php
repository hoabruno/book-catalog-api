<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Author;
use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BookWriteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_cannot_write(): void
    {
        $this->postJson('/api/v1/books', [])->assertUnauthorized();
    }

    #[Test]
    public function a_token_without_the_write_ability_is_refused(): void
    {
        Sanctum::actingAs(User::factory()->editor()->create(), ['catalog:read']);

        $this->postJson('/api/v1/books', $this->payload())->assertForbidden();
    }

    #[Test]
    public function readers_are_refused_by_the_policy_even_with_the_ability(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['catalog:write']);

        $this->postJson('/api/v1/books', $this->payload())->assertForbidden();
    }

    #[Test]
    public function an_editor_creates_a_book_and_the_isbn10_is_normalised(): void
    {
        Sanctum::actingAs(User::factory()->editor()->create(), ['catalog:write']);

        $this->postJson('/api/v1/books', $this->payload(['isbn' => '2-07-036024-5']))
            ->assertCreated()
            ->assertJsonPath('data.isbn', '9782070360246')
            ->assertJsonPath('data.slug', 'l-etranger')
            ->assertJsonPath('data.format.label', 'Broché');

        $this->assertDatabaseHas('books', ['isbn' => '9782070360246', 'title' => "L'Étranger"]);
    }

    #[Test]
    public function the_same_isbn_cannot_be_added_twice_in_any_form(): void
    {
        Sanctum::actingAs(User::factory()->editor()->create(), ['catalog:write']);
        Book::factory()->create(['isbn' => '9782070360246']);

        $this->postJson('/api/v1/books', $this->payload(['isbn' => '2070360245']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('isbn');
    }

    #[Test]
    public function invalid_payloads_are_rejected(): void
    {
        Sanctum::actingAs(User::factory()->editor()->create(), ['catalog:write']);

        $this->postJson('/api/v1/books', ['isbn' => '9782070360247', 'format' => 'scroll', 'author_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['isbn', 'format', 'author_id', 'title']);
    }

    #[Test]
    public function duplicate_titles_get_distinct_slugs(): void
    {
        Sanctum::actingAs(User::factory()->editor()->create(), ['catalog:write']);

        $this->postJson('/api/v1/books', $this->payload())->assertJsonPath('data.slug', 'l-etranger');
        $this->postJson('/api/v1/books', $this->payload(['isbn' => '9780306406157']))->assertJsonPath('data.slug', 'l-etranger-2');
    }

    #[Test]
    public function an_editor_replaces_then_deletes_a_book(): void
    {
        Sanctum::actingAs(User::factory()->editor()->create(), ['catalog:write']);
        $book = Book::factory()->create();

        $this->putJson("/api/v1/books/{$book->slug}", $this->payload(['isbn' => $book->isbn->value]))
            ->assertOk()
            ->assertJsonPath('data.title', "L'Étranger")
            ->assertJsonPath('data.slug', 'l-etranger');

        $this->deleteJson('/api/v1/books/l-etranger')->assertNoContent();
        $this->assertSoftDeleted($book);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'author_id' => Author::factory()->create()->id,
            'isbn' => '9782070360246',
            'title' => "L'Étranger",
            'format' => 'paperback',
            'language' => 'fr',
            'published_year' => 1942,
            'page_count' => 186,
            ...$overrides,
        ];
    }
}
