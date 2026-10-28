<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthorController;
use App\Http\Controllers\Api\V1\BookController;
use App\Http\Controllers\Api\V1\BookFileController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('throttle:api')->group(function (): void {
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:login')->name('tokens.store');

    // Public reads.
    Route::get('books', [BookController::class, 'index'])->name('books.index');
    Route::get('books/{book}', [BookController::class, 'show'])->name('books.show');
    Route::get('authors', [AuthorController::class, 'index'])->name('authors.index');
    Route::get('files/{file}/download', [BookFileController::class, 'download'])->name('files.download');

    // Writes: a Sanctum token with the catalog:write ability, then the policy.
    Route::middleware(['auth:sanctum'])->group(function (): void {
        Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

        Route::middleware('abilities:catalog:write')->group(function (): void {
            Route::post('books', [BookController::class, 'store'])->name('books.store');
            Route::put('books/{book}', [BookController::class, 'update'])->name('books.update');
            Route::delete('books/{book}', [BookController::class, 'destroy'])->name('books.destroy');
            Route::post('books/{book}/files', [BookFileController::class, 'store'])
                ->middleware('throttle:uploads')->name('books.files.store');
            Route::delete('files/{file}', [BookFileController::class, 'destroy'])->name('files.destroy');
        });
    });
});
