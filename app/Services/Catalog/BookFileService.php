<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Exceptions\StorageUnavailable;
use App\Jobs\ReplicateBookFile;
use App\Models\Book;
use App\Models\BookFile;
use App\Models\User;
use App\Storage\StorageRegistry;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Stores book files on the registered backends and serves them back.
 *
 * The primary copy goes to the requested (or default) backend, then a queued
 * job replicates it. Reads try every known copy in turn, so losing one
 * backend does not make files unavailable.
 */
final readonly class BookFileService
{
    public function __construct(
        private StorageRegistry $storage,
        private DatabaseManager $db,
        private LoggerInterface $logger,
        private ?string $replica,
    ) {}

    public function store(Book $book, UploadedFile $upload, ?User $uploader = null, ?string $backend = null): BookFile
    {
        $checksum = (string) hash_file('sha256', $upload->getRealPath());

        // Same content uploaded twice for the same book: keep the first copy.
        $existing = $book->files()->where('checksum', $checksum)->first();
        if ($existing !== null) {
            return $existing;
        }

        $target = $backend !== null ? $this->storage->get($backend) : $this->storage->default();
        $extension = $upload->guessExtension() ?? $upload->getClientOriginalExtension();
        $path = sprintf('books/%d/%s.%s', $book->id, $checksum, $extension);

        $stream = fopen($upload->getRealPath(), 'rb') ?: throw StorageUnavailable::onBackend($target->name(), $path);
        try {
            $target->write($path, $stream);
        } finally {
            fclose($stream);
        }

        try {
            $file = $this->db->connection()->transaction(fn (): BookFile => $book->files()->create([
                'uploaded_by' => $uploader?->id,
                'backend' => $target->name(),
                'replicas' => [],
                'path' => $path,
                'original_name' => $upload->getClientOriginalName(),
                'mime_type' => $upload->getMimeType() ?? 'application/octet-stream',
                'size' => $upload->getSize(),
                'checksum' => $checksum,
            ]));
        } catch (Throwable $e) {
            // Compensate: no orphan object left behind on the backend.
            $target->delete($path);
            throw $e;
        }

        if ($this->replica !== null && $this->replica !== $target->name() && $this->storage->has($this->replica)) {
            ReplicateBookFile::dispatch($file->id, $this->replica);
        }

        return $file;
    }

    /**
     * Opens the first reachable copy of the file.
     *
     * @return array{stream: resource, backend: string}
     *
     * @throws StorageUnavailable when no copy can be read
     */
    public function open(BookFile $file): array
    {
        foreach ($file->locations() as $backend) {
            if (! $this->storage->has($backend)) {
                continue;
            }

            try {
                return ['stream' => $this->storage->get($backend)->readStream($file->path), 'backend' => $backend];
            } catch (StorageUnavailable $e) {
                $this->logger->warning('Book file copy unreadable, trying the next one.', [
                    'file_id' => $file->id,
                    'backend' => $backend,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw StorageUnavailable::everywhere($file->path);
    }

    public function delete(BookFile $file): void
    {
        $locations = $file->locations();
        $path = $file->path;

        $connection = $this->db->connection();
        $connection->transaction(function () use ($connection, $file, $locations, $path): void {
            $file->delete();

            // Objects are removed only once the row is gone for good.
            $connection->afterCommit(function () use ($locations, $path): void {
                foreach ($locations as $backend) {
                    try {
                        $this->storage->get($backend)->delete($path);
                    } catch (Throwable $e) {
                        $this->logger->error('Could not delete a book file copy.', [
                            'backend' => $backend, 'path' => $path, 'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
        });
    }
}
