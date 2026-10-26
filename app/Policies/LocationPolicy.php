<?php

namespace App\Policies;

use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->canManageInventory();
    }

    public function update(User $user, Location $location): bool
    {
        return $user->canManageInventory();
    }

    public function delete(User $user, Location $location): bool
    {
        return $user->canManageInventory();
    }
}
