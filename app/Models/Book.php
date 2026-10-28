<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\AsIsbn;
use App\Enums\BookFormat;
use App\Models\Builders\BookQuery;
use App\Observers\FlushCatalogCache;
use App\ValueObjects\Isbn;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $author_id
 * @property Isbn $isbn
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property BookFormat $format
 * @property string $language
 * @property int|null $published_year
 * @property int|null $page_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Author $author
 * @property-read Collection<int, BookFile> $files
 */
#[Fillable(['author_id', 'isbn', 'title', 'slug', 'summary', 'format', 'language', 'published_year', 'page_count'])]
#[ObservedBy(FlushCatalogCache::class)]
#[UseEloquentBuilder(BookQuery::class)]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<Author, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    /**
     * @return HasMany<BookFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(BookFile::class)->latest('id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * URL slug of a title. Apostrophes become separators first, so French
     * elisions read well: "L'Étranger" gives "l-etranger", not "letranger".
     */
    public static function slugFor(string $title): string
    {
        return Str::slug(str_replace(["'", '’'], ' ', $title)) ?: 'livre';
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'isbn' => AsIsbn::class,
            'format' => BookFormat::class,
            'published_year' => 'integer',
            'page_count' => 'integer',
        ];
    }
}
