<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Exceptions\StorageUnavailable;

/**
 * A named place where catalog files live (S3 bucket, WebDAV share...).
 *
 * Implementations are registered in the StorageRegistry; the rest of the
 * application never talks to a filesystem disk directly.
 */
interface FileStorage
{
    public function name(): string;

    /**
     * @param  resource  $stream
     *
     * @throws StorageUnavailable
     */
    public function write(string $path, $stream): void;

    /**
     * @return resource
     *
     * @throws StorageUnavailable when the file cannot be read
     */
    public function readStream(string $path);

    public function exists(string $path): bool;

    public function delete(string $path): void;
}
