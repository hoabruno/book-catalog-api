<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookFileRequest;
use App\Http\Resources\BookFileResource;
use App\Models\Book;
use App\Models\BookFile;
use App\Services\Catalog\BookFileService;
use App\Support\FileDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BookFileController extends Controller
{
    public function __construct(private readonly BookFileService $files) {}

    /**
     * Attach a file to a book.
     *
     * Stored on `backend` (or the default one), then replicated in the
     * background. Uploading the same content twice returns the existing file.
     */
    public function store(StoreBookFileRequest $request, Book $book): JsonResponse
    {
        Gate::authorize('manageFiles', $book);

        $file = $this->files->store($book, $request->upload(), $request->user(), $request->backend());

        return BookFileResource::make($file)
            ->response()
            ->setStatusCode($file->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    /**
     * Download a file, from the first backend that can serve it.
     *
     * @unauthenticated
     */
    public function download(BookFile $file): StreamedResponse
    {
        return FileDownload::stream($this->files, $file);
    }

    /**
     * Delete a file and all its copies.
     */
    public function destroy(BookFile $file): Response
    {
        Gate::authorize('manageFiles', $file->book);

        $this->files->delete($file);

        return response()->noContent();
    }
}
