<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ValueObjects\Isbn;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsbnTest extends TestCase
{
    #[Test]
    public function it_keeps_a_valid_isbn13_and_strips_separators(): void
    {
        $this->assertSame('9782070360246', Isbn::fromString('978-2-07-036024-6')->value);
    }

    #[Test]
    public function it_converts_isbn10_to_isbn13(): void
    {
        $this->assertSame('9782070360246', Isbn::fromString('2-07-036024-5')->value);
        $this->assertSame('9780306406157', Isbn::fromString('0-306-40615-2')->value);
    }

    #[Test]
    public function it_accepts_an_x_check_digit_on_isbn10(): void
    {
        $this->assertSame('9780804429573', Isbn::fromString('080442957X')->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputs(): iterable
    {
        yield 'wrong isbn13 checksum' => ['9782070360247'];
        yield 'wrong isbn10 checksum' => ['2070360248'];
        yield 'too short' => ['97820703'];
        yield 'letters' => ['97820703602AB'];
        yield 'empty' => [''];
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function it_rejects_invalid_input(string $input): void
    {
        $this->assertNull(Isbn::tryFrom($input));

        $this->expectException(InvalidArgumentException::class);
        Isbn::fromString($input);
    }

    #[Test]
    public function equal_isbns_compare_by_value(): void
    {
        $this->assertTrue(Isbn::fromString('2070360245')->equals(Isbn::fromString('9782070360246')));
        $this->assertSame('978-2070360246', Isbn::fromString('9782070360246')->formatted());
    }
}
