<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Catalog\BookRepository;
use App\Data\BookFilters;
use App\Models\Book;
use App\Support\CatalogCache;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decorator adding a cache in front of any BookRepository.
 *
 * Consumers depend on the interface only; the container decides whether
 * they get the cached or the raw implementation (see CatalogServiceProvider).
 */
final readonly class CachedBookRepository implements BookRepository
{
    public function __construct(
        private BookRepository $inner,
        private CatalogCache $cache,
    ) {}

    public function search(BookFilters $filters): CursorPaginator
    {
        return $this->cache->remember(
            'search:'.$filters->cacheKey(),
            fn (): CursorPaginator => $this->inner->search($filters),
        );
    }

    public function findBySlug(string $slug): ?Book
    {
        return $this->cache->remember(
            'book:'.$slug,
            fn () => $this->inner->findBySlug($slug),
        );
    }

    public function authors(): Collection
    {
        return $this->cache->remember('authors', fn (): Collection => $this->inner->authors());
    }
}
