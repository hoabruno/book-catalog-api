<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Editor = 'editor';
    case Reader = 'reader';

    public function canManageCatalog(): bool
    {
        return match ($this) {
            self::Admin, self::Editor => true,
            self::Reader => false,
        };
    }

    /**
     * Abilities granted to API tokens issued for this role.
     *
     * @return list<string>
     */
    public function tokenAbilities(): array
    {
        return $this->canManageCatalog() ? ['catalog:read', 'catalog:write'] : ['catalog:read'];
    }
}
