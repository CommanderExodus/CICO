<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/**
 * Ein- und Austragen in die Benachrichtigungsgruppen: Fundmeldungen für Verwalter und
 * Admins, neue Registrierungen nur für Admins.
 */
class UpdateNotificationPreferences
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $user, array $input): User
    {
        Gate::forUser($user)->authorize('manageNotifications', $user);

        $validated = Validator::make($input, [
            'notify_found_reports' => ['required', 'boolean'],
            'notify_registrations' => ['sometimes', 'boolean'],
        ])->validate();

        $user->notify_found_reports = (bool) $validated['notify_found_reports'];

        if ($user->isAdmin()) {
            $user->notify_registrations = (bool) ($validated['notify_registrations'] ?? false);
        }

        DB::transaction(fn () => $user->save());

        return $user;
    }
}
