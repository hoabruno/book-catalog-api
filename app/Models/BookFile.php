<?php

declare(strict_types=1);

namespace App\Models;

use App\Observers\FlushCatalogCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $book_id
 * @property int|null $uploaded_by
 * @property string $backend
 * @property list<string> $replicas
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $checksum
 * @property-read Book $book
 */
#[Fillable(['book_id', 'uploaded_by', 'backend', 'replicas', 'path', 'original_name', 'mime_type', 'size', 'checksum'])]
#[ObservedBy(FlushCatalogCache::class)]
class BookFile extends Model
{
    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class)->withTrashed();
    }

    /**
     * Every backend holding a copy, primary first.
     *
     * @return list<string>
     */
    public function locations(): array
    {
        return array_values(array_unique([$this->backend, ...$this->replicas]));
    }

    public function hasCopyOn(string $backend): bool
    {
        return in_array($backend, $this->locations(), true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'replicas' => 'array',
            'size' => 'integer',
        ];
    }
}
