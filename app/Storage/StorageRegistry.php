<?php

declare(strict_types=1);

namespace App\Storage;

use App\Contracts\Storage\FileStorage;
use App\Exceptions\UnknownStorageBackend;
use InvalidArgumentException;

/**
 * Registry of the storage backends available to the catalog, by name.
 *
 * Backends are registered once at boot (CatalogServiceProvider) from
 * config/catalog.php. Adding a backend (Ceph RGW, Azure, local NFS...) means
 * declaring a disk and one config line: no consumer changes, and no enum to
 * extend, since backend names are data stored with each file.
 */
final class StorageRegistry
{
    /** @var array<string, FileStorage> */
    private array $backends = [];

    public function __construct(private string $defaultName) {}

    public function register(FileStorage $storage): self
    {
        if (isset($this->backends[$storage->name()])) {
            throw new InvalidArgumentException("Storage backend [{$storage->name()}] is already registered.");
        }

        $this->backends[$storage->name()] = $storage;

        return $this;
    }

    public function get(string $name): FileStorage
    {
        return $this->backends[$name] ?? throw UnknownStorageBackend::named($name, $this->names());
    }

    public function has(string $name): bool
    {
        return isset($this->backends[$name]);
    }

    public function default(): FileStorage
    {
        return $this->get($this->defaultName);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->backends);
    }
}
