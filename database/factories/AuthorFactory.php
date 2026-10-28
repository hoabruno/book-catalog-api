<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Author;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Author>
 */
final class AuthorFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->name();
        $birth = fake()->numberBetween(1700, 1980);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'biography' => fake()->optional()->paragraph(),
            'birth_year' => $birth,
            'death_year' => fake()->optional()->numberBetween($birth + 30, min($birth + 95, 2025)),
        ];
    }
}
