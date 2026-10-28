<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Contracts\Catalog\BookRepository;
use App\Enums\BookFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookRequest;
use App\Models\Book;
use App\Services\Catalog\BookCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Back office forms. Same request, policy and service as the API.
 */
final class BookController extends Controller
{
    public function __construct(
        private readonly BookCatalogService $catalog,
        private readonly BookRepository $books,
    ) {}

    public function create(): View
    {
        Gate::authorize('create', Book::class);

        return $this->form(new Book);
    }

    public function store(BookRequest $request): RedirectResponse
    {
        Gate::authorize('create', Book::class);

        $book = $this->catalog->create($request->toData());

        return to_route('catalog.show', $book)->with('status', 'Livre ajouté au catalogue.');
    }

    public function edit(Book $book): View
    {
        Gate::authorize('update', $book);

        return $this->form($book);
    }

    public function update(BookRequest $request, Book $book): RedirectResponse
    {
        Gate::authorize('update', $book);

        $book = $this->catalog->update($book, $request->toData());

        return to_route('catalog.show', $book)->with('status', 'Livre mis à jour.');
    }

    public function destroy(Book $book): RedirectResponse
    {
        Gate::authorize('delete', $book);

        $this->catalog->delete($book);

        return to_route('catalog.index')->with('status', "« {$book->title} » a été retiré du catalogue.");
    }

    private function form(Book $book): View
    {
        return view('books.form', [
            'book' => $book,
            'authors' => $this->books->authors(),
            'formats' => BookFormat::options(),
        ]);
    }
}
