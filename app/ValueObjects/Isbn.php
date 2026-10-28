<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An ISBN, always held in its canonical 13-digit form.
 *
 * ISBN-10 input is accepted and converted, so the catalog stores and compares
 * a single representation (the books.isbn column is unique on it).
 */
final readonly class Isbn implements JsonSerializable, Stringable
{
    private function __construct(public string $value) {}

    public static function fromString(string $input): self
    {
        return self::tryFrom($input)
            ?? throw new InvalidArgumentException("Invalid ISBN: {$input}");
    }

    public static function tryFrom(string $input): ?self
    {
        $digits = strtoupper((string) preg_replace('/[\s-]/', '', $input));

        return match (true) {
            preg_match('/^\d{13}$/', $digits) === 1 => self::isValid13($digits) ? new self($digits) : null,
            preg_match('/^\d{9}[\dX]$/', $digits) === 1 => self::isValid10($digits) ? new self(self::convert10($digits)) : null,
            default => null,
        };
    }

    /**
     * Human-readable form: 978-2-07-036024-5 style grouping is publisher
     * dependent, so only the EAN prefix is split off.
     */
    public function formatted(): string
    {
        return substr($this->value, 0, 3).'-'.substr($this->value, 3);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }

    private static function isValid13(string $digits): bool
    {
        return self::checkDigit13(substr($digits, 0, 12)) === (int) $digits[12];
    }

    private static function isValid10(string $digits): bool
    {
        $sum = 0;
        foreach (str_split($digits) as $i => $char) {
            $sum += ($char === 'X' ? 10 : (int) $char) * (10 - $i);
        }

        return $sum % 11 === 0;
    }

    private static function convert10(string $digits): string
    {
        $body = '978'.substr($digits, 0, 9);

        return $body.self::checkDigit13($body);
    }

    private static function checkDigit13(string $twelveDigits): int
    {
        $sum = 0;
        foreach (str_split($twelveDigits) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10;
    }
}
