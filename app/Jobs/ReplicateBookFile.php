<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BookFile;
use App\Storage\StorageRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

/**
 * Copies a book file to a second backend, then records the replica once its
 * checksum has been verified on the target.
 */
final class ReplicateBookFile implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> seconds between attempts */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $fileId,
        public readonly string $target,
    ) {
        // Never start before the upload transaction has committed.
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return "{$this->fileId}:{$this->target}";
    }

    public function handle(StorageRegistry $storage, ConnectionInterface $db): void
    {
        $file = BookFile::query()->find($this->fileId);
        if ($file === null || $file->hasCopyOn($this->target)) {
            return;
        }

        $source = $storage->get($file->backend)->readStream($file->path);
        try {
            $storage->get($this->target)->write($file->path, $source);
        } finally {
            if (is_resource($source)) {
                fclose($source);
            }
        }

        $copy = $storage->get($this->target)->readStream($file->path);
        $context = hash_init('sha256');
        hash_update_stream($context, $copy);
        fclose($copy);
        if (hash_final($context) !== $file->checksum) {
            throw new RuntimeException("Replica of file {$file->id} on [{$this->target}] failed checksum verification.");
        }

        $db->transaction(function () use ($file): void {
            // Row lock: two replications of the same file never overwrite each other's entry.
            $fresh = BookFile::query()->lockForUpdate()->findOrFail($file->id);
            $fresh->replicas = array_values(array_unique([...$fresh->replicas, $this->target]));
            $fresh->save();
        });
    }
}
