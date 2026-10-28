<?php

declare(strict_types=1);

namespace App\Services\Import;

final class ImportReport
{
    public int $imported = 0;

    /** @var array<int, string> line number => reason */
    public array $rejected = [];

    public function reject(int $line, string $reason): void
    {
        $this->rejected[$line] = $reason;
    }
}
