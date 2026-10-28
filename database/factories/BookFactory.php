<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BookFormat;
use App\Models\Author;
use App\Models\Book;
use App\ValueObjects\Isbn;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Book>
 */
final class BookFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = Str::title(rtrim(fake()->unique()->sentence(fake()->numberBetween(2, 5)), '.'));

        return [
            'author_id' => Author::factory(),
            'isbn' => self::fakeIsbn(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'summary' => fake()->paragraph(),
            'format' => fake()->randomElement(BookFormat::cases()),
            'language' => fake()->randomElement(['fr', 'fr', 'en', 'de']),
            'published_year' => fake()->numberBetween(1800, 2025),
            'page_count' => fake()->numberBetween(60, 1200),
        ];
    }

    /**
     * A checksum-valid ISBN-13 in a range reserved for test data.
     */
    public static function fakeIsbn(): Isbn
    {
        $body = '979108'.str_pad((string) fake()->unique()->numberBetween(0, 999_999), 6, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($body) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return Isbn::fromString($body.((10 - $sum % 10) % 10));
    }
}
