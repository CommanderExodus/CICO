<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ChangeUserRole
{
    public function handle(User $actor, User $user, Role $role): User
    {
        Gate::forUser($actor)->authorize('changeRole', $user);

        DB::transaction(function () use ($user, $role) {
            // Admin-Zeilen sperren, damit zwei gleichzeitige Herabstufungen nicht den letzten Admin entfernen.
            $admins = User::query()->where('role', Role::Admin)->lockForUpdate()->pluck('id');

            if ($user->role === Role::Admin && $role !== Role::Admin && $admins->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Es muss mindestens ein Admin bestehen bleiben.',
                ]);
            }

            $user->forceFill(['role' => $role])->save();
        });

        return $user;
    }
}
