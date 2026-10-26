<?php

namespace App\Enums;

/**
 * Rollen mit jeweils eigenem Rechteumfang (R21).
 */
enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Borrower = 'borrower';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Verwalter',
            self::Borrower => 'Ausleiher',
        };
    }

    public function canManageInventory(): bool
    {
        return $this !== self::Borrower;
    }
}
