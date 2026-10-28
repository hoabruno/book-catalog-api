<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Storage\StorageRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

final class StoreBookFileRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(StorageRegistry $storage): array
    {
        return [
            'file' => [
                'required', 'file',
                'mimes:'.implode(',', config('catalog.files.mimes')),
                'max:'.config('catalog.files.max_kilobytes'),
            ],
            // Optional: the default backend is used when omitted.
            'backend' => ['nullable', 'string', Rule::in($storage->names())],
        ];
    }

    public function upload(): UploadedFile
    {
        /** @var UploadedFile */
        return $this->file('file');
    }

    public function backend(): ?string
    {
        return $this->filled('backend') ? $this->string('backend')->value() : null;
    }
}
