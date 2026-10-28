<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookFileRequest;
use App\Models\Book;
use App\Models\BookFile;
use App\Services\Catalog\BookFileService;
use App\Support\FileDownload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BookFileController extends Controller
{
    public function __construct(private readonly BookFileService $files) {}

    public function store(StoreBookFileRequest $request, Book $book): RedirectResponse
    {
        Gate::authorize('manageFiles', $book);

        $file = $this->files->store($book, $request->upload(), $request->user(), $request->backend());

        return to_route('catalog.show', $book)->with('status', $file->wasRecentlyCreated
            ? "Fichier enregistré sur « {$file->backend} », réplication en cours."
            : 'Ce fichier était déjà attaché à ce livre.');
    }

    public function download(BookFile $file): StreamedResponse
    {
        return FileDownload::stream($this->files, $file);
    }

    public function destroy(BookFile $file): RedirectResponse
    {
        $book = $file->book;
        Gate::authorize('manageFiles', $book);

        $this->files->delete($file);

        return to_route('catalog.show', $book)->with('status', 'Fichier supprimé de tous les stockages.');
    }
}
