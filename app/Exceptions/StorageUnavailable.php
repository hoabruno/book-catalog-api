<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

final class StorageUnavailable extends RuntimeException
{
    public static function onBackend(string $backend, string $path, ?Throwable $previous = null): self
    {
        return new self("Storage backend [{$backend}] could not access [{$path}].", 0, $previous);
    }

    public static function everywhere(string $path): self
    {
        return new self("No storage backend could serve [{$path}].");
    }
}
