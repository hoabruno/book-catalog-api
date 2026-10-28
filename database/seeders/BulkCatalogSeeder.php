<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Support\CatalogCache;
use Illuminate\Database\Seeder;

/**
 * Large synthetic catalog for local performance checks (needs dev
 * dependencies): php artisan db:seed --class=BulkCatalogSeeder
 */
final class BulkCatalogSeeder extends Seeder
{
    public function run(CatalogCache $cache): void
    {
        $authors = Author::factory()->count(500)->create();

        Book::withoutEvents(function () use ($authors): void {
            foreach (range(1, 40) as $batch) {
                // An explicit author_id keeps the factory from creating one author per book.
                $rows = Book::factory()->count(500)->make([
                    'author_id' => fn (): int => $authors->random()->id,
                ])->map(fn (Book $book): array => [
                    ...$book->getAttributes(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                Book::query()->insert($rows->all());
            }
        });

        $cache->flush();
        $this->command?->info('20 000 books generated.');
    }
}
