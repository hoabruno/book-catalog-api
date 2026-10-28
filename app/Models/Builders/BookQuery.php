<?php

declare(strict_types=1);

namespace App\Models\Builders;

use App\Data\BookFilters;
use App\Enums\BookFormat;
use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query builder of the Book model: named, composable filters instead of
 * ad-hoc where() calls in controllers.
 *
 * @extends Builder<Book>
 */
final class BookQuery extends Builder
{
    public function search(?string $term): self
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $this;
        }

        if ($this->getModel()->getConnection()->getDriverName() === 'mysql') {
            // Boolean mode with a trailing wildcard per word: "miser hug" finds "Les Misérables, Hugo".
            $words = preg_split('/\s+/', preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $term) ?? '') ?: [];
            $expression = implode(' ', array_map(fn (string $w): string => '+'.$w.'*', array_filter($words)));

            return $this->where(fn (Builder $q) => $q
                ->whereFullText(['title', 'summary'], $expression, ['mode' => 'boolean'])
                ->orWhereRelation('author', 'name', 'like', '%'.$term.'%'));
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $this->where(fn (Builder $q) => $q
            ->where('title', 'like', $like)
            ->orWhere('summary', 'like', $like)
            ->orWhereRelation('author', 'name', 'like', $like));
    }

    public function byAuthor(?int $authorId): self
    {
        return $authorId === null ? $this : $this->where('author_id', $authorId);
    }

    public function ofFormat(?BookFormat $format): self
    {
        return $format === null ? $this : $this->where('format', $format);
    }

    public function publishedBetween(?int $from, ?int $to): self
    {
        return $this
            ->when($from !== null, fn (self $q) => $q->where('published_year', '>=', $from))
            ->when($to !== null, fn (self $q) => $q->where('published_year', '<=', $to));
    }

    public function applyFilters(BookFilters $filters): self
    {
        return $this
            ->search($filters->search)
            ->byAuthor($filters->authorId)
            ->ofFormat($filters->format)
            ->publishedBetween($filters->yearFrom, $filters->yearTo);
    }
}
