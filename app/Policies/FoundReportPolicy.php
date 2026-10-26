<?php

namespace App\Policies;

use App\Models\FoundReport;
use App\Models\User;

/**
 * Fundmeldungen enthalten Kontaktdaten der Finder und sind deshalb nur für Verwalter sichtbar.
 */
class FoundReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function view(User $user, FoundReport $report): bool
    {
        return $user->canManageInventory();
    }

    public function resolve(User $user, FoundReport $report): bool
    {
        return $user->canManageInventory();
    }
}
