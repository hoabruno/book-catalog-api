<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Contracts\Catalog\BookRepository;
use App\Enums\BookFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\SearchBooksRequest;
use App\Storage\StorageRegistry;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CatalogController extends Controller
{
    public function __construct(private readonly BookRepository $books) {}

    public function index(SearchBooksRequest $request): View
    {
        return view('catalog.index', [
            'books' => $this->books->search($request->toFilters())->withQueryString(),
            'authors' => $this->books->authors(),
            'formats' => BookFormat::options(),
            'filters' => $request->toFilters(),
        ]);
    }

    public function show(string $book, StorageRegistry $storage): View
    {
        return view('catalog.show', [
            'book' => $this->books->findBySlug($book) ?? throw new NotFoundHttpException,
            'backends' => $storage->names(),
        ]);
    }
}
