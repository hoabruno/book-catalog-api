<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | File storage backends
    |--------------------------------------------------------------------------
    |
    | Every backend is a named entry of the StorageRegistry, backed by one of
    | the filesystem disks declared in config/filesystems.php. New uploads go
    | to "default"; when "replica" is set, a queued job copies each file to
    | that second backend, and downloads fall back to it when the primary
    | copy is unavailable.
    |
    */

    'storage' => [
        'default' => env('CATALOG_STORAGE_DEFAULT', 's3'),
        'replica' => env('CATALOG_STORAGE_REPLICA', 'webdav'),
        'backends' => [
            's3' => 'books_s3',
            'webdav' => 'books_webdav',
        ],
    ],

    'files' => [
        'max_kilobytes' => (int) env('CATALOG_FILE_MAX_KB', 20480),
        'mimes' => ['pdf', 'epub', 'txt', 'jpg', 'png', 'webp'],
    ],

    'cache' => [
        // Null uses the default cache store (Redis in production).
        'store' => env('CATALOG_CACHE_STORE'),
        'ttl' => (int) env('CATALOG_CACHE_TTL', 600),
    ],

    'pagination' => [
        'default' => 24,
        'max' => 100,
    ],

];
