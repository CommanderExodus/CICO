<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Lehnt ein wartendes Konto ab und löscht es. Wartende Konten haben keine Historie,
 * daher ist das Löschen hier – anders als bei aktiven Konten – unproblematisch.
 */
class RejectRegistration
{
    public function handle(User $actor, User $user): void
    {
        Gate::forUser($actor)->authorize('reject', $user);

        if (! $user->isPending()) {
            throw ValidationException::withMessages([
                'user' => 'Nur Konten, die auf Freischaltung warten, können abgelehnt werden.',
            ]);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
