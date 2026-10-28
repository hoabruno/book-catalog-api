<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Namespaced cache for catalog reads.
 *
 * Every key embeds a version number; flushing increments it, which makes all
 * previous entries unreachable at once (they expire on their own TTL). This
 * works on any store, unlike cache tags, and costs one atomic INCR on Redis.
 */
final readonly class CatalogCache
{
    private const string VERSION_KEY = 'catalog:version';

    public function __construct(
        private CacheFactory $cache,
        private ?string $store,
        private int $ttl,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function remember(string $key, Closure $callback): mixed
    {
        return $this->repository()->remember(
            sprintf('catalog:v%d:%s', $this->version(), $key),
            $this->ttl,
            $callback,
        );
    }

    public function flush(): void
    {
        $store = $this->repository();
        $store->add(self::VERSION_KEY, 1, null);
        $store->increment(self::VERSION_KEY);
    }

    private function version(): int
    {
        return (int) $this->repository()->rememberForever(self::VERSION_KEY, fn (): int => 1);
    }

    private function repository(): Repository
    {
        return $this->cache->store($this->store);
    }
}
