<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Data\BookData;
use App\Models\Book;
use Illuminate\Database\ConnectionInterface;

/**
 * Write side of the catalog: every create, update and delete goes through
 * here, inside a transaction, whatever the entry point (API, web, CLI import).
 */
final readonly class BookCatalogService
{
    public function __construct(private ConnectionInterface $db) {}

    public function create(BookData $data): Book
    {
        return $this->db->transaction(fn (): Book => Book::query()->create([
            ...$data->toAttributes(),
            'slug' => $this->uniqueSlug($data->title),
        ]));
    }

    public function update(Book $book, BookData $data): Book
    {
        return $this->db->transaction(function () use ($book, $data): Book {
            $book->fill($data->toAttributes());

            if ($book->isDirty('title')) {
                $book->slug = $this->uniqueSlug($data->title, ignoreId: $book->id);
            }

            $book->save();

            return $book;
        });
    }

    /**
     * Soft delete: the book leaves the catalog, its files are kept and it can
     * be restored.
     */
    public function delete(Book $book): void
    {
        $book->delete();
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Book::slugFor($title);
        $taken = Book::withTrashed()
            ->where(fn ($q) => $q->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
            ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
            ->pluck('slug')
            ->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $suffix = 2;
        while (in_array("{$base}-{$suffix}", $taken, true)) {
            $suffix++;
        }

        return "{$base}-{$suffix}";
    }
}
