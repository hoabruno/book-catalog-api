<?php

declare(strict_types=1);

namespace App\Enums;

enum BookFormat: string
{
    case Hardcover = 'hardcover';
    case Paperback = 'paperback';
    case Ebook = 'ebook';
    case Audiobook = 'audiobook';

    public function label(): string
    {
        return match ($this) {
            self::Hardcover => 'Relié',
            self::Paperback => 'Broché',
            self::Ebook => 'Livre numérique',
            self::Audiobook => 'Livre audio',
        };
    }

    /**
     * @return array<string, string> value => label, for select inputs
     */
    public static function options(): array
    {
        return array_column(
            array_map(fn (self $format): array => [$format->value, $format->label()], self::cases()),
            1,
            0,
        );
    }
}
