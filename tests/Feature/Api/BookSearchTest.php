<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\BookFormat;
use App\Models\Author;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BookSearchTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_books_newest_first_with_their_author(): void
    {
        $hugo = Author::factory()->create(['name' => 'Victor Hugo']);
        Book::factory()->for($hugo)->create(['title' => 'Notre-Dame de Paris', 'published_year' => 1831]);
        Book::factory()->for($hugo)->create(['title' => 'Les Misérables', 'published_year' => 1862]);

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Les Misérables')
            ->assertJsonPath('data.0.author.name', 'Victor Hugo')
            ->assertJsonPath('data.1.title', 'Notre-Dame de Paris')
            ->assertJsonStructure(['data', 'links', 'meta' => ['next_cursor', 'prev_cursor']]);
    }

    #[Test]
    public function it_filters_by_text_author_format_and_years(): void
    {
        $zola = Author::factory()->create(['name' => 'Émile Zola']);
        $verne = Author::factory()->create(['name' => 'Jules Verne']);
        Book::factory()->for($zola)->create(['title' => 'Germinal', 'format' => BookFormat::Paperback, 'published_year' => 1885]);
        Book::factory()->for($zola)->create(['title' => 'Nana', 'format' => BookFormat::Ebook, 'published_year' => 1880]);
        Book::factory()->for($verne)->create(['title' => 'Michel Strogoff', 'format' => BookFormat::Paperback, 'published_year' => 1876]);

        $this->getJson('/api/v1/books?q=germ')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Germinal');
        $this->getJson('/api/v1/books?q=verne')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Michel Strogoff');
        $this->getJson("/api/v1/books?author_id={$zola->id}")->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/books?format=paperback')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/books?year_from=1877&year_to=1882')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Nana');
    }

    #[Test]
    public function it_paginates_with_a_cursor(): void
    {
        Book::factory()->count(5)->sequence(fn ($s) => ['published_year' => 2000 + $s->index])->create();

        $first = $this->getJson('/api/v1/books?per_page=2')->assertJsonCount(2, 'data');
        $second = $this->getJson('/api/v1/books?per_page=2&cursor='.$first->json('meta.next_cursor'))->assertJsonCount(2, 'data');

        $this->assertSame([2004, 2003], array_column($first->json('data'), 'published_year'));
        $this->assertSame([2002, 2001], array_column($second->json('data'), 'published_year'));
    }

    #[Test]
    public function invalid_filters_are_rejected(): void
    {
        $this->getJson('/api/v1/books?format=scroll&year_from=1900&year_to=1800&per_page=1000')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['format', 'year_to', 'per_page']);
    }

    #[Test]
    public function it_shows_a_book_by_slug_and_hides_deleted_ones(): void
    {
        $book = Book::factory()->create();

        $this->getJson("/api/v1/books/{$book->slug}")
            ->assertOk()
            ->assertJsonPath('data.isbn', $book->isbn->value)
            ->assertJsonPath('data.files', []);

        $book->delete();
        $this->getJson("/api/v1/books/{$book->slug}")->assertNotFound();
    }

    #[Test]
    public function it_lists_authors_with_book_counts(): void
    {
        Book::factory()->count(2)->for(Author::factory()->state(['name' => 'Balzac']))->create();

        $this->getJson('/api/v1/authors')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Balzac')
            ->assertJsonPath('data.0.books_count', 2);
    }
}
