<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Catalog\BookRepository;
use App\Data\BookFilters;
use App\Models\Author;
use App\Models\Book;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

final class EloquentBookRepository implements BookRepository
{
    public function search(BookFilters $filters): CursorPaginator
    {
        // Cursor pagination stays O(page size) however deep the client goes,
        // where OFFSET would scan every skipped row on a large catalog.
        return Book::query()
            ->applyFilters($filters)
            ->with('author:id,name,slug')
            ->withCount('files')
            ->orderByDesc('published_year')
            ->orderByDesc('id')
            ->cursorPaginate(perPage: $filters->perPage, cursor: $filters->cursor);
    }

    public function findBySlug(string $slug): ?Book
    {
        return Book::query()
            ->with(['author', 'files'])
            ->where('slug', $slug)
            ->first();
    }

    public function authors(): Collection
    {
        return Author::query()
            ->withCount('books')
            ->orderBy('name')
            ->get();
    }
}
