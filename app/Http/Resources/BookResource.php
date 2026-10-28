<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Book
 */
final class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'isbn' => $this->isbn->value,
            'title' => $this->title,
            'summary' => $this->summary,
            'format' => [
                'value' => $this->format->value,
                'label' => $this->format->label(),
            ],
            'language' => $this->language,
            'published_year' => $this->published_year,
            'page_count' => $this->page_count,
            'author' => AuthorResource::make($this->whenLoaded('author')),
            'files_count' => $this->whenCounted('files'),
            'files' => BookFileResource::collection($this->whenLoaded('files')),
            'links' => [
                'self' => route('api.v1.books.show', $this->resource),
                'web' => route('catalog.show', $this->resource),
            ],
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
