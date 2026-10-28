<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Catalog\BookRepository;
use App\Data\BookFilters;
use App\Models\Book;
use App\Repositories\CachedBookRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CatalogCacheTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_container_hands_out_the_cached_decorator(): void
    {
        $this->assertInstanceOf(CachedBookRepository::class, $this->app->make(BookRepository::class));
    }

    #[Test]
    public function repeated_reads_hit_the_cache_and_writes_invalidate_it(): void
    {
        $books = $this->app->make(BookRepository::class);
        $book = Book::factory()->create(['title' => 'Avant']);

        $books->findBySlug($book->slug);
        DB::enableQueryLog();
        $this->assertSame('Avant', $books->findBySlug($book->slug)?->title);
        $this->assertSame([], DB::getQueryLog(), 'The second read must not query the database.');

        $book->update(['title' => 'Après']);

        // Fresh resolution: same singleton, so this also checks it is shared.
        $this->assertSame('Après', $this->app->make(BookRepository::class)->findBySlug($book->slug)?->title);
    }

    #[Test]
    public function a_new_book_shows_up_in_cached_searches(): void
    {
        $books = $this->app->make(BookRepository::class);
        Book::factory()->create();
        $this->assertCount(1, $books->search(new BookFilters)->items());

        Book::factory()->create();

        $this->assertCount(2, $books->search(new BookFilters)->items());
    }
}
