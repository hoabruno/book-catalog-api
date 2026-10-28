<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CatalogImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_demo_catalog_imports_and_reimports_without_duplicates(): void
    {
        $this->command('catalog:import', ['path' => database_path('data/classics.csv'), '--chunk' => 7])
            ->expectsOutputToContain('30 books imported or updated.')
            ->assertSuccessful();

        $this->command('catalog:import', ['path' => database_path('data/classics.csv')])->assertSuccessful();

        $this->assertSame(30, Book::query()->count());
        $this->assertSame(18, Author::query()->count());
        $this->assertSame(3, Author::query()->where('slug', 'victor-hugo')->sole()->books()->count());
    }

    #[Test]
    public function invalid_rows_are_reported_and_skipped(): void
    {
        $csv = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($csv, implode("\n", [
            'isbn,title,author,author_birth_year,author_death_year,format,language,published_year,page_count,summary',
            '9780306406157,Valide,Auteur Un,,,paperback,fr,1990,100,',
            '9780306406158,Mauvais ISBN,Auteur Un,,,paperback,fr,1990,100,',
            '9780804429573,Mauvais format,Auteur Deux,,,scroll,fr,1990,100,',
        ]));

        $this->command('catalog:import', ['path' => $csv])
            ->expectsOutputToContain('1 books imported or updated.')
            ->expectsOutputToContain('Line 3')
            ->expectsOutputToContain('Line 4')
            ->assertFailed();

        $this->assertSame(['Valide'], Book::query()->pluck('title')->all());
        unlink($csv);
    }

    #[Test]
    public function an_imported_title_that_collides_gets_a_distinct_slug(): void
    {
        Book::factory()->create(['title' => 'Candide', 'slug' => 'candide']);

        $this->command('catalog:import', ['path' => database_path('data/classics.csv')])->assertSuccessful();

        $this->assertMatchesRegularExpression('/^candide-\d{6}$/', Book::query()->where('title', 'Candide')->latest('id')->value('slug'));
    }
}
