<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BookFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookFile
 */
final class BookFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'checksum_sha256' => $this->checksum,
            'stored_on' => $this->locations(),
            'download_url' => route('api.v1.files.download', $this->resource),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
