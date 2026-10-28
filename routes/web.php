<?php

declare(strict_types=1);

use App\Http\Controllers\Web\BookController;
use App\Http\Controllers\Web\BookFileController;
use App\Http\Controllers\Web\CatalogController;
use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('livres/{book}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('fichiers/{file}', [BookFileController::class, 'download'])->name('files.download');

Route::middleware('guest')->group(function (): void {
    Route::get('connexion', [SessionController::class, 'create'])->name('login');
    Route::post('connexion', [SessionController::class, 'store'])->middleware('throttle:login');
});

Route::middleware('auth')->group(function (): void {
    Route::post('deconnexion', [SessionController::class, 'destroy'])->name('logout');

    Route::prefix('gestion')->name('manage.')->group(function (): void {
        Route::get('livres/nouveau', [BookController::class, 'create'])->name('books.create');
        Route::post('livres', [BookController::class, 'store'])->name('books.store');
        Route::get('livres/{book}/modifier', [BookController::class, 'edit'])->name('books.edit');
        Route::put('livres/{book}', [BookController::class, 'update'])->name('books.update');
        Route::delete('livres/{book}', [BookController::class, 'destroy'])->name('books.destroy');
        Route::post('livres/{book}/fichiers', [BookFileController::class, 'store'])
            ->middleware('throttle:uploads')->name('books.files.store');
        Route::delete('fichiers/{file}', [BookFileController::class, 'destroy'])->name('files.destroy');
    });
});
