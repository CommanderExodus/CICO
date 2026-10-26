<?php

use App\Actions\Users\UpdateNotificationPreferences;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Benachrichtigungen')] class extends Component {
    public bool $notify_found_reports = false;

    public bool $notify_registrations = false;

    public function mount(): void
    {
        $user = Auth::user();

        Gate::authorize('manageNotifications', $user);

        $this->notify_found_reports = $user->notify_found_reports;
        $this->notify_registrations = $user->notify_registrations;
    }

    public function save(UpdateNotificationPreferences $update): void
    {
        $update->handle(Auth::user(), [
            'notify_found_reports' => $this->notify_found_reports,
            'notify_registrations' => $this->notify_registrations,
        ]);

        $this->dispatch('notifications-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">Benachrichtigungen</flux:heading>

    <x-pages::settings.layout heading="Benachrichtigungen" subheading="Trage dich in die Gruppen ein, über die du per E-Mail informiert werden möchtest.">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:switch wire:model="notify_found_reports" label="Fundmeldungen"
                description="Wenn jemand über die öffentliche Statusseite einen Fund meldet. Ist niemand eingetragen, werden alle Verwalter und Admins informiert." />

            @if (auth()->user()->isAdmin())
                <flux:switch wire:model="notify_registrations" label="Neue Registrierungen"
                    description="Wenn sich jemand registriert und auf Freischaltung wartet." />
            @endif

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                <x-action-message on="notifications-updated">{{ __('Saved.') }}</x-action-message>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
