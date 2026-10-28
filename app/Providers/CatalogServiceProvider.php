<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Catalog\BookRepository;
use App\Repositories\CachedBookRepository;
use App\Repositories\EloquentBookRepository;
use App\Services\Catalog\BookFileService;
use App\Storage\DiskFileStorage;
use App\Storage\StorageRegistry;
use App\Support\CatalogCache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\WebDAV\WebDAVAdapter;
use Sabre\DAV\Client;

/**
 * Wires the catalog: storage registry, repository decorator, file service.
 * The only place that knows which concrete classes are used.
 */
final class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StorageRegistry::class, function (Application $app): StorageRegistry {
            $config = $app->make(Config::class);
            $registry = new StorageRegistry((string) $config->get('catalog.storage.default'));

            /** @var array<string, string> $backends */
            $backends = $config->get('catalog.storage.backends', []);
            foreach ($backends as $name => $disk) {
                $registry->register(new DiskFileStorage($name, $disk, $app->make('filesystem')));
            }

            return $registry;
        });

        $this->app->singleton(CatalogCache::class, fn (Application $app): CatalogCache => new CatalogCache(
            $app->make('cache'),
            $app->make(Config::class)->get('catalog.cache.store'),
            (int) $app->make(Config::class)->get('catalog.cache.ttl'),
        ));

        // Decorator: consumers type-hint BookRepository and get the cached one.
        $this->app->singleton(BookRepository::class, fn (Application $app): BookRepository => new CachedBookRepository(
            $app->make(EloquentBookRepository::class),
            $app->make(CatalogCache::class),
        ));

        $this->app->when(BookFileService::class)
            ->needs('$replica')
            ->giveConfig('catalog.storage.replica');
    }

    public function boot(): void
    {
        // Flysystem ships a WebDAV adapter but Laravel has no "webdav" driver.
        Storage::extend('webdav', function (Application $app, array $config): FilesystemAdapter {
            $adapter = new WebDAVAdapter(
                new Client([
                    'baseUri' => $config['base_uri'],
                    'userName' => $config['username'] ?? null,
                    'password' => $config['password'] ?? null,
                ]),
                $config['prefix'] ?? '',
            );

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }
}
