<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Wer welche Benachrichtigung bekommt. Verwalter und Admins tragen sich selbst in die
 * Gruppen ein; zusätzlich zählt immer die aktuelle Rolle, damit Herabgestufte nichts
 * mehr erhalten.
 */
class NotificationRecipients
{
    /**
     * Fundmeldungen (R14): die eingetragenen Verwalter und Admins. Ist niemand eingetragen,
     * gehen sie an alle Verwalter und Admins, damit keine Meldung ungelesen bleibt.
     *
     * @return Collection<int, User>
     */
    public function foundReports(): Collection
    {
        $staff = User::query()->whereIn('role', [Role::Manager, Role::Admin]);

        $subscribed = (clone $staff)->where('notify_found_reports', true)->get();

        return $subscribed->isNotEmpty() ? $subscribed : $staff->get();
    }

    /**
     * Neue Registrierungen: nur eingetragene Admins, ohne Rückfallebene – das Badge am
     * Menüpunkt „Benutzer“ zeigt wartende Konten ohnehin an.
     *
     * @return Collection<int, User>
     */
    public function registrations(): Collection
    {
        return User::query()
            ->where('role', Role::Admin)
            ->where('notify_registrations', true)
            ->get();
    }
}
