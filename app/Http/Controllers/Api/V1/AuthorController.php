<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Catalog\BookRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AuthorController extends Controller
{
    /**
     * List authors with their number of books.
     *
     * @unauthenticated
     */
    public function index(BookRepository $books): AnonymousResourceCollection
    {
        return AuthorResource::collection($books->authors());
    }
}
