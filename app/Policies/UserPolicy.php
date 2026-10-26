<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function changeRole(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function reject(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Benachrichtigungsgruppen gibt es nur für Verwalter und Admins, jeweils für sich selbst.
     */
    public function manageNotifications(User $user, User $target): bool
    {
        return $user->is($target) && $user->canManageInventory();
    }
}
