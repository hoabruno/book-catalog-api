<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\BookFormat;
use App\ValueObjects\Isbn;

/**
 * Everything needed to create or replace a book, already validated.
 */
final readonly class BookData
{
    public function __construct(
        public int $authorId,
        public Isbn $isbn,
        public string $title,
        public BookFormat $format,
        public string $language = 'fr',
        public ?string $summary = null,
        public ?int $publishedYear = null,
        public ?int $pageCount = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'author_id' => $this->authorId,
            'isbn' => $this->isbn,
            'title' => $this->title,
            'format' => $this->format,
            'language' => $this->language,
            'summary' => $this->summary,
            'published_year' => $this->publishedYear,
            'page_count' => $this->pageCount,
        ];
    }
}
