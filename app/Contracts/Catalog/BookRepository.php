<?php

declare(strict_types=1);

namespace App\Contracts\Catalog;

use App\Data\BookFilters;
use App\Models\Author;
use App\Models\Book;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Read side of the catalog. Writes go through BookCatalogService.
 */
interface BookRepository
{
    /**
     * @return CursorPaginator<int, Book>
     */
    public function search(BookFilters $filters): CursorPaginator;

    public function findBySlug(string $slug): ?Book;

    /**
     * Authors with their number of books, for filters and listings.
     *
     * @return Collection<int, Author>
     */
    public function authors(): Collection;
}
