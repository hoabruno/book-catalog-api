<?php

declare(strict_types=1);

namespace App\Observers;

use App\Support\CatalogCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidates cached catalog reads whenever a book, author or file changes.
 *
 * Runs after the transaction commits, so a concurrent request cannot
 * re-cache the old state between the flush and the commit.
 */
final readonly class FlushCatalogCache implements ShouldHandleEventsAfterCommit
{
    public function __construct(private CatalogCache $cache) {}

    public function saved(Model $model): void
    {
        $this->cache->flush();
    }

    public function deleted(Model $model): void
    {
        $this->cache->flush();
    }

    public function restored(Model $model): void
    {
        $this->cache->flush();
    }
}
