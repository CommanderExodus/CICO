<x-layouts::auth title="Freischaltung ausstehend">
    <div class="flex flex-col gap-6">
        <x-auth-header
            title="Fast geschafft"
            description="Dein Konto wartet auf Freischaltung durch einen Admin."
        />

        <flux:callout icon="clock" color="amber">
            <flux:callout.text>
                Sobald ein Admin dich freigeschaltet hat, kannst du den Bestand durchsuchen und Gegenstände reservieren.
                Bis dahin sind keine Inhalte sichtbar.
            </flux:callout.text>
        </flux:callout>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <flux:button type="submit" variant="ghost" class="w-full" data-test="logout-button">{{ __('Log out') }}</flux:button>
        </form>
    </div>
</x-layouts::auth>
