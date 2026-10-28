<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

/**
 * The catalog is public to read; only editors and admins change it.
 */
final class BookPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Book $book): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageCatalog();
    }

    public function update(User $user, Book $book): bool
    {
        return $user->canManageCatalog();
    }

    public function delete(User $user, Book $book): bool
    {
        return $user->canManageCatalog();
    }

    public function manageFiles(User $user, Book $book): bool
    {
        return $user->canManageCatalog();
    }
}
