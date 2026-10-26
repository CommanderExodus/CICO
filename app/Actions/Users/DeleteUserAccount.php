<?php

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use App\Support\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Löscht das eigene Konto. Konten mit Ausleihhistorie bleiben erhalten, weil die
 * Fremdschlüssel auf loans/found_reports das Löschen verhindern (Revisionssicherheit).
 */
class DeleteUserAccount
{
    public function handle(User $user): void
    {
        try {
            DB::transaction(function () use ($user) {
                $admins = User::query()->where('role', Role::Admin)->lockForUpdate()->pluck('id')->count();

                if ($user->isAdmin() && $admins <= 1) {
                    throw ValidationException::withMessages([
                        'password' => 'Als einziger Admin kannst du dein Konto nicht löschen.',
                    ]);
                }

                $user->tokens()->delete();
                $user->delete();
            });
        } catch (QueryException $e) {
            if (! ConstraintViolation::isForeignKey($e)) {
                throw $e;
            }

            throw ValidationException::withMessages([
                'password' => 'Dein Konto ist mit Ausleihen oder Fundmeldungen verknüpft und bleibt zur Nachvollziehbarkeit erhalten. Bitte wende dich an einen Admin.',
            ]);
        }
    }
}
