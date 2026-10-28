<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Author
 */
final class AuthorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'biography' => $this->whenHas('biography'),
            'birth_year' => $this->whenHas('birth_year'),
            'death_year' => $this->whenHas('death_year'),
            'books_count' => $this->whenCounted('books'),
        ];
    }
}
