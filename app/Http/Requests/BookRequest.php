<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\BookData;
use App\Enums\BookFormat;
use App\Models\Book;
use App\Rules\ValidIsbn;
use App\ValueObjects\Isbn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create (POST) or full replacement (PUT) of a book, for the API and the web
 * back office alike. Authorization is left to the BookPolicy via routes.
 */
final class BookRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $book = $this->route('book');

        return [
            'author_id' => ['required', 'integer', Rule::exists('authors', 'id')],
            'isbn' => [
                'required', 'string', new ValidIsbn,
                Rule::unique('books', 'isbn')->ignore($book instanceof Book ? $book->id : null),
            ],
            'title' => ['required', 'string', 'max:255'],
            'format' => ['required', Rule::enum(BookFormat::class)],
            'language' => ['sometimes', 'string', 'size:2', 'alpha'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'published_year' => ['nullable', 'integer', 'between:1450,'.(now()->year + 1)],
            'page_count' => ['nullable', 'integer', 'between:1,65535'],
        ];
    }

    public function toData(): BookData
    {
        return new BookData(
            authorId: $this->integer('author_id'),
            isbn: Isbn::fromString($this->string('isbn')->value()),
            title: $this->string('title')->trim()->value(),
            format: $this->enum('format', BookFormat::class) ?? BookFormat::Paperback,
            language: $this->string('language', 'fr')->lower()->value(),
            summary: $this->filled('summary') ? $this->string('summary')->trim()->value() : null,
            publishedYear: $this->filled('published_year') ? $this->integer('published_year') : null,
            pageCount: $this->filled('page_count') ? $this->integer('page_count') : null,
        );
    }

    /**
     * Normalise the ISBN before the unique check, so "2-07-036024-5" and
     * "9782070360246" are recognised as the same book.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('isbn')) && ($isbn = Isbn::tryFrom($this->input('isbn'))) !== null) {
            $this->merge(['isbn' => $isbn->value]);
        }
    }
}
