<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Enums\BookFormat;
use App\Models\Author;
use App\Models\Book;
use App\Rules\ValidIsbn;
use App\Support\CatalogCache;
use App\ValueObjects\Isbn;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Bulk import of books from a CSV file.
 *
 * The file is streamed (LazyCollection) and written in chunks with upsert:
 * memory stays flat and each chunk costs a handful of queries, whatever the
 * file size. Re-running the same file updates books instead of duplicating
 * them (the ISBN is the natural key).
 *
 * Columns: isbn, title, author, author_birth_year, author_death_year, format,
 * language, published_year, page_count, summary.
 */
final readonly class CatalogCsvImporter
{
    private const array COLUMNS = [
        'isbn', 'title', 'author', 'author_birth_year', 'author_death_year',
        'format', 'language', 'published_year', 'page_count', 'summary',
    ];

    public function __construct(
        private ConnectionInterface $db,
        private CatalogCache $cache,
    ) {}

    public function import(string $path, int $chunkSize = 500): ImportReport
    {
        $report = new ImportReport;

        $this->rows($path)
            ->chunk($chunkSize)
            ->each(function (LazyCollection $chunk) use ($report): void {
                $valid = [];
                foreach ($chunk as $line => $row) {
                    $validator = Validator::make($row, $this->rules());
                    if ($validator->fails()) {
                        $report->reject($line, (string) $validator->errors()->first());

                        continue;
                    }
                    $valid[$line] = $validator->validated();
                }

                if ($valid !== []) {
                    $this->db->transaction(fn () => $this->write($valid));
                    $report->imported += count($valid);
                }
            });

        // upsert() bypasses model events, so the cache is flushed explicitly.
        $this->cache->flush();

        return $report;
    }

    /**
     * @return LazyCollection<int, array<string, string|null>> keyed by line number
     */
    private function rows(string $path): LazyCollection
    {
        return LazyCollection::make(function () use ($path) {
            $handle = @fopen($path, 'rb') ?: throw new RuntimeException("Cannot read {$path}.");
            try {
                $header = fgetcsv($handle, escape: '');
                if ($header === false || array_diff(self::COLUMNS, $header) !== []) {
                    throw new RuntimeException('Missing CSV columns. Expected: '.implode(', ', self::COLUMNS).'.');
                }

                $header = array_map(strval(...), $header);
                $line = 1;
                while (($values = fgetcsv($handle, escape: '')) !== false) {
                    $line++;
                    if ($values === [null]) {
                        continue;
                    }
                    $row = array_combine($header, array_pad($values, count($header), null));
                    yield $line => array_map(fn (?string $v): ?string => ($v = trim((string) $v)) === '' ? null : $v, $row);
                }
            } finally {
                fclose($handle);
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'isbn' => ['required', new ValidIsbn],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'author_birth_year' => ['nullable', 'integer'],
            'author_death_year' => ['nullable', 'integer'],
            'format' => ['required', Rule::enum(BookFormat::class)],
            'language' => ['nullable', 'alpha', 'size:2'],
            'published_year' => ['nullable', 'integer', 'between:1450,2100'],
            'page_count' => ['nullable', 'integer', 'between:1,65535'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function write(array $rows): void
    {
        $now = now();

        // 1. Authors: one upsert, then one query to map slugs to ids.
        $authors = collect($rows)
            ->mapWithKeys(fn (array $r): array => [Str::slug($r['author']) => [
                'slug' => Str::slug($r['author']),
                'name' => $r['author'],
                'birth_year' => $r['author_birth_year'] ?? null,
                'death_year' => $r['author_death_year'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ]]);
        Author::query()->upsert($authors->values()->all(), ['slug'], ['name', 'birth_year', 'death_year', 'updated_at']);
        $authorIds = Author::query()->whereIn('slug', $authors->keys())->pluck('id', 'slug');

        // 2. Slugs: keep the title slug unless another ISBN already owns it.
        $books = collect($rows)->map(fn (array $r): array => [
            'isbn' => Isbn::fromString((string) $r['isbn'])->value,
            'slug' => Book::slugFor($r['title']),
            'r' => $r,
        ]);
        $owners = Book::withTrashed()->whereIn('slug', $books->pluck('slug'))->pluck('isbn', 'slug')->all();

        $records = [];
        foreach ($books as ['isbn' => $isbn, 'slug' => $slug, 'r' => $r]) {
            if ((isset($owners[$slug]) && $owners[$slug] !== $isbn) || isset($records[$slug])) {
                $slug .= '-'.substr($isbn, -6);
            }
            $owners[$slug] = $isbn;

            $records[$slug] = [
                'isbn' => $isbn,
                'slug' => $slug,
                'title' => $r['title'],
                'author_id' => $authorIds[Str::slug($r['author'])],
                'format' => $r['format'],
                'language' => strtolower($r['language'] ?? 'fr'),
                'published_year' => $r['published_year'] ?? null,
                'page_count' => $r['page_count'] ?? null,
                'summary' => $r['summary'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // 3. Books: one upsert keyed on ISBN; existing slugs are never rewritten.
        Book::query()->upsert(
            array_values($records),
            ['isbn'],
            ['title', 'author_id', 'format', 'language', 'published_year', 'page_count', 'summary', 'updated_at'],
        );
    }
}
