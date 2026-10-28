<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\Storage\FileStorage;
use App\Exceptions\StorageUnavailable;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Throwable;

/**
 * FileStorage on top of a Laravel filesystem disk (any Flysystem adapter).
 *
 * The disk is resolved on every call rather than captured once, so a disk
 * swapped at runtime (Storage::fake() in tests, config reload) is honoured.
 */
final readonly class DiskFileStorage implements FileStorage
{
    public function __construct(
        private string $name,
        private string $disk,
        private FilesystemFactory $filesystems,
    ) {}

    public function name(): string
    {
        return $this->name;
    }

    public function write(string $path, $stream): void
    {
        try {
            $written = $this->disk()->writeStream($path, $stream);
        } catch (Throwable $e) {
            throw StorageUnavailable::onBackend($this->name, $path, $e);
        }

        if ($written === false) {
            throw StorageUnavailable::onBackend($this->name, $path);
        }
    }

    public function readStream(string $path)
    {
        try {
            $stream = $this->disk()->readStream($path);
        } catch (Throwable $e) {
            throw StorageUnavailable::onBackend($this->name, $path, $e);
        }

        return $stream ?? throw StorageUnavailable::onBackend($this->name, $path);
    }

    public function exists(string $path): bool
    {
        try {
            return $this->disk()->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    public function delete(string $path): void
    {
        try {
            $this->disk()->delete($path);
        } catch (Throwable $e) {
            throw StorageUnavailable::onBackend($this->name, $path, $e);
        }
    }

    private function disk(): Filesystem
    {
        return $this->filesystems->disk($this->disk);
    }
}
