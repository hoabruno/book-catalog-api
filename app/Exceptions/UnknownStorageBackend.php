<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

final class UnknownStorageBackend extends InvalidArgumentException
{
    /**
     * @param  list<string>  $known
     */
    public static function named(string $name, array $known): self
    {
        return new self(sprintf('Unknown storage backend [%s]. Registered: %s.', $name, implode(', ', $known) ?: 'none'));
    }
}
