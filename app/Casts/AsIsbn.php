<?php

declare(strict_types=1);

namespace App\Casts;

use App\ValueObjects\Isbn;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<Isbn, Isbn|string>
 */
final class AsIsbn implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Isbn
    {
        return $value === null ? null : Isbn::fromString((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof Isbn => $value->value,
            default => Isbn::fromString($value)->value,
        };
    }
}
