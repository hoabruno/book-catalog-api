<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BookFormat;

/**
 * Validated catalog search criteria, independent of HTTP.
 */
final readonly class BookFilters
{
    public function __construct(
        public ?string $search = null,
        public ?int $authorId = null,
        public ?BookFormat $format = null,
        public ?int $yearFrom = null,
        public ?int $yearTo = null,
        public int $perPage = 24,
        public ?string $cursor = null,
    ) {}

    /**
     * Stable key of the criteria, used to cache result pages.
     */
    public function cacheKey(): string
    {
        return hash('xxh128', serialize([
            $this->search, $this->authorId, $this->format?->value,
            $this->yearFrom, $this->yearTo, $this->perPage, $this->cursor,
        ]));
    }
}
