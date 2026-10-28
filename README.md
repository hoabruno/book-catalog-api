# Book Catalog API

A book catalog built with **Laravel 13** and **PHP 8.5**: a documented REST API,
a small Blade front end, and book files stored on **S3** and **WebDAV** through
a storage registry, with background replication and automatic fallback.

The point of the project is less the feature set than the way it is built:
small classes with one job each, wired by the service container, and the
framework's conventions followed rather than worked around.

## Stack

| Concern | Choice |
| --- | --- |
| Runtime | PHP 8.5 (FPM), Laravel 13 |
| Database | MySQL 8.4 (full-text index), SQLite for tests |
| Cache, sessions, queue | Redis |
| File backends | Garage (S3-compatible, Ceph RGW works the same) and rclone WebDAV |
| Auth | Sanctum: session for the web UI, bearer tokens with abilities for the API |
| API docs | OpenAPI generated from the code by Scramble, served at `/docs/api` |
| Quality | PHPUnit, Larastan level 8, Pint, GitHub Actions (SQLite and MySQL) |

## Architecture

```
HTTP (API v1 / Blade)
  └─ FormRequest ........ validation, then toData() / toFilters() -> readonly DTOs
  └─ Policy ............. who may do what (BookPolicy)
  └─ Controller ......... thin: no query, no business rule
        ├─ BookRepository (interface)      reads
        │     └─ CachedBookRepository      decorator, Redis, versioned keys
        │           └─ EloquentBookRepository
        ├─ BookCatalogService              writes, in transactions
        └─ BookFileService                 files
              └─ StorageRegistry           named backends
                    ├─ "s3"     -> DiskFileStorage -> Flysystem S3 adapter
                    └─ "webdav" -> DiskFileStorage -> Flysystem WebDAV adapter
```

### Storage registry

`App\Storage\StorageRegistry` maps a backend name to a `FileStorage`
implementation. It is built once, in `CatalogServiceProvider`, from
`config/catalog.php`:

```php
'storage' => [
    'default' => env('CATALOG_STORAGE_DEFAULT', 's3'),
    'replica' => env('CATALOG_STORAGE_REPLICA', 'webdav'),
    'backends' => ['s3' => 'books_s3', 'webdav' => 'books_webdav'],
],
```

- An upload goes to the default backend (or the one requested), and its
  SHA-256 is recorded.
- `ReplicateBookFile` (queued, unique, retried with backoff) copies it to the
  replica, **verifies the checksum on the target**, and only then records it.
- A download tries each known copy in turn; the `X-Served-From` response
  header shows which backend answered. Lose the S3 bucket and files are still
  served from WebDAV.
- Adding a backend (Ceph, Azure, NFS...) is one disk and one config line. The
  names are data stored with each file, which is why they are not an enum.

Laravel has no WebDAV driver: `CatalogServiceProvider` registers one with
`Storage::extend()` on top of `league/flysystem-webdav`.

### Other patterns worth a look

| Where | What |
| --- | --- |
| `app/ValueObjects/Isbn.php` | Immutable value object; ISBN-10 is converted so one canonical form is stored |
| `app/Casts/AsIsbn.php` | Custom Eloquent cast: `$book->isbn` is an `Isbn`, never a raw string |
| `app/Models/Builders/BookQuery.php` | Dedicated query builder (`#[UseEloquentBuilder]`) with composable filters |
| `app/Support/CatalogCache.php` | Cache invalidation by version bump: works on any store, one `INCR` on Redis |
| `app/Observers/FlushCatalogCache.php` | Runs after commit, so stale data cannot be re-cached mid-transaction |
| `app/Services/Import/CatalogCsvImporter.php` | Streamed CSV (LazyCollection), chunked `upsert`, flat memory, idempotent |
| `app/Providers/AppServiceProvider.php` | `Model::shouldBeStrict()` outside production: N+1 queries throw |
| `app/Http/Controllers/Api/V1/TokenController.php` | Constant-time credential check, abilities derived from the role enum |

### Performance

- Cursor pagination: page 1 000 costs the same as page 1, unlike `OFFSET`.
- Composite indexes matching the listing filters; MySQL full-text search with
  a `LIKE` fallback on other drivers.
- Eager loading everywhere (lazy loading throws in development and tests).
- OPcache with timestamps validation off, authoritative class map.
- `php artisan db:seed --class=BulkCatalogSeeder` generates 20 000 books locally.

## API

Full reference: `/docs/api`. In short (prefix `/api/v1`):

| Method | Path | Access |
| --- | --- | --- |
| `POST` | `/tokens` | public, rate limited |
| `GET` | `/books?q=&author_id=&format=&year_from=&year_to=&per_page=&cursor=` | public |
| `GET` | `/books/{slug}` | public |
| `GET` | `/authors` | public |
| `GET` | `/files/{id}/download` | public |
| `POST` `PUT` `DELETE` | `/books`, `/books/{slug}` | token with `catalog:write` + editor role |
| `POST` | `/books/{slug}/files` (multipart `file`, optional `backend`) | same |
| `DELETE` | `/files/{id}` | same |

```bash
TOKEN=$(curl -s https://$HOST/api/v1/tokens -H 'Accept: application/json' \
  -d email=editor@example.com -d password=... -d device_name=cli | jq -r .token)

curl -s https://$HOST/api/v1/books/germinal/files -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json' -F file=@extract.pdf
```

## Running it

### Tests and checks

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```

Tests use in-memory SQLite and fake disks; CI also runs them against MySQL.

### Production (docker-compose)

`docker-compose.yml` runs nginx, PHP-FPM, a queue worker, the scheduler,
MySQL, Redis, Garage and WebDAV. Only nginx is exposed (through a Traefik
reverse proxy); everything else sits on an internal network with no
published port.

1. Create `.env` from `.env.example`, generating every secret on the server.
2. `docker-compose up -d --build`; migrations run when `app` starts.
3. Initialise Garage once (layout, bucket, key):
   ```bash
   G="docker exec book-catalog-garage /garage"
   $G layout assign -z dc1 -c 5G "$($G node id -q | cut -d@ -f1)"
   $G layout apply --version 1
   $G bucket create books
   $G key import --yes -n catalog "$BOOKS_S3_KEY" "$BOOKS_S3_SECRET"
   $G bucket allow --read --write books --key catalog
   ```
4. Load the demo catalog and create an editor (password read from stdin):
   ```bash
   docker exec book-catalog-app php artisan db:seed --force
   printf '%s' "$PASSWORD" | docker exec -i book-catalog-app \
     php artisan catalog:create-user editor@example.com editor --name=Editor
   ```

## Demo data

`database/data/classics.csv` holds 30 public domain classics. Their ISBNs are
**fictitious** (valid checksums in a single range), not those of real editions.

## License

MIT
