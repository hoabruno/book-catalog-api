<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Storage\FileStorage;
use App\Exceptions\UnknownStorageBackend;
use App\Storage\StorageRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StorageRegistryTest extends TestCase
{
    #[Test]
    public function it_resolves_registered_backends_by_name(): void
    {
        $registry = (new StorageRegistry('s3'))
            ->register($s3 = $this->backend('s3'))
            ->register($webdav = $this->backend('webdav'));

        $this->assertSame($s3, $registry->get('s3'));
        $this->assertSame($webdav, $registry->get('webdav'));
        $this->assertSame($s3, $registry->default());
        $this->assertSame(['s3', 'webdav'], $registry->names());
        $this->assertTrue($registry->has('webdav'));
        $this->assertFalse($registry->has('ftp'));
    }

    #[Test]
    public function an_unknown_backend_is_reported_with_the_known_ones(): void
    {
        $registry = (new StorageRegistry('s3'))->register($this->backend('s3'));

        $this->expectException(UnknownStorageBackend::class);
        $this->expectExceptionMessage('Unknown storage backend [ftp]. Registered: s3.');
        $registry->get('ftp');
    }

    #[Test]
    public function a_name_cannot_be_registered_twice(): void
    {
        $registry = (new StorageRegistry('s3'))->register($this->backend('s3'));

        $this->expectException(InvalidArgumentException::class);
        $registry->register($this->backend('s3'));
    }

    private function backend(string $name): FileStorage
    {
        $storage = $this->createStub(FileStorage::class);
        $storage->method('name')->willReturn($name);

        return $storage;
    }
}
