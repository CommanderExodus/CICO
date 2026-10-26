<?php

namespace App\Enums;

/**
 * Rollen mit jeweils eigenem Rechteumfang (R21). Neue Registrierungen sind „pending“,
 * bis ein Admin sie freischaltet; bis dahin haben sie keinerlei Rechte.
 */
enum Role: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Borrower = 'borrower';
    case Pending = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Manager => 'Verwalter',
            self::Borrower => 'Ausleiher',
            self::Pending => 'wartet auf Freischaltung',
        };
    }

    public function canManageInventory(): bool
    {
        return $this === self::Admin || $this === self::Manager;
    }
}
