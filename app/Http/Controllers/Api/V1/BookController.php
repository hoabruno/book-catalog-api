<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Catalog\BookRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookRequest;
use App\Http\Requests\SearchBooksRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use App\Services\Catalog\BookCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Thin controller: validation in form requests, rules in the policy, reads in
 * the repository, writes in the service.
 */
final class BookController extends Controller
{
    public function __construct(
        private readonly BookRepository $books,
        private readonly BookCatalogService $catalog,
    ) {}

    /**
     * Search the catalog.
     *
     * Cursor-paginated, newest publication first. `q` searches titles,
     * summaries and author names.
     *
     * @unauthenticated
     */
    public function index(SearchBooksRequest $request): AnonymousResourceCollection
    {
        return BookResource::collection(
            $this->books->search($request->toFilters())->withQueryString(),
        );
    }

    /**
     * Show a book with its author and files.
     *
     * @unauthenticated
     */
    public function show(string $book): BookResource
    {
        return BookResource::make(
            $this->books->findBySlug($book) ?? throw new NotFoundHttpException,
        );
    }

    /**
     * Add a book. ISBN-10 input is converted to ISBN-13.
     */
    public function store(BookRequest $request): JsonResponse
    {
        Gate::authorize('create', Book::class);

        $book = $this->catalog->create($request->toData());

        return BookResource::make($book->load('author'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Replace a book.
     */
    public function update(BookRequest $request, Book $book): BookResource
    {
        Gate::authorize('update', $book);

        return BookResource::make($this->catalog->update($book, $request->toData())->load('author'));
    }

    /**
     * Remove a book from the catalog (soft delete).
     */
    public function destroy(Book $book): Response
    {
        Gate::authorize('delete', $book);

        $this->catalog->delete($book);

        return response()->noContent();
    }
}
