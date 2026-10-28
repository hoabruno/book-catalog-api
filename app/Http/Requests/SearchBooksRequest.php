<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\BookFilters;
use App\Enums\BookFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SearchBooksRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'author_id' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', Rule::enum(BookFormat::class)],
            'year_from' => ['nullable', 'integer', 'between:1450,2100'],
            'year_to' => ['nullable', 'integer', 'between:1450,2100', 'gte:year_from'],
            'per_page' => ['nullable', 'integer', 'between:1,'.config('catalog.pagination.max')],
            'cursor' => ['nullable', 'string', 'max:512'],
        ];
    }

    public function toFilters(): BookFilters
    {
        return new BookFilters(
            search: $this->filled('q') ? $this->string('q')->trim()->value() : null,
            authorId: $this->filled('author_id') ? $this->integer('author_id') : null,
            format: $this->enum('format', BookFormat::class),
            yearFrom: $this->filled('year_from') ? $this->integer('year_from') : null,
            yearTo: $this->filled('year_to') ? $this->integer('year_to') : null,
            perPage: $this->integer('per_page', (int) config('catalog.pagination.default')),
            cursor: $this->filled('cursor') ? $this->string('cursor')->value() : null,
        );
    }
}
