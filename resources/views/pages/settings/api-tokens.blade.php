<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('MCP-Zugang')] class extends Component {
    public string $tokenName = '';

    public ?string $plainTextToken = null;

    /**
     * Persönliches Token für den MCP-Server (R26). Der Klartext wird nur einmal angezeigt;
     * gespeichert ist ausschließlich sein Hash.
     */
    public function createToken(): void
    {
        $validated = $this->validate([
            'tokenName' => ['required', 'string', 'max:100'],
        ], attributes: ['tokenName' => 'Name']);

        $this->plainTextToken = Auth::user()->createToken($validated['tokenName'], ['mcp'])->plainTextToken;
        $this->reset('tokenName');
    }

    public function revokeToken(int $tokenId): void
    {
        Auth::user()->tokens()->whereKey($tokenId)->delete();

        Flux::toast(variant: 'success', text: 'Token widerrufen.');
    }

    #[Computed]
    public function tokens(): Collection
    {
        return Auth::user()->tokens()->latest()->get();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">MCP-Zugang</flux:heading>

    <x-pages::settings.layout heading="MCP-Zugang" subheading="Persönliche Tokens, mit denen KI-Assistenten unter deinem Konto auf das Inventar zugreifen.">
        <div class="flex flex-col gap-6">
            <flux:callout icon="key">
                <flux:callout.text>
                    MCP-Server: <span class="font-mono">{{ url('/mcp') }}</span><br>
                    Anmeldung per Header <span class="font-mono">Authorization: Bearer &lt;Token&gt;</span>.
                    Der Assistent hat genau deine Rechte.
                </flux:callout.text>
            </flux:callout>

            <form wire:submit="createToken" class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:input wire:model="tokenName" label="Name des Clients" placeholder="z. B. Claude Desktop" required maxlength="100" />
                </div>
                <flux:button type="submit" variant="primary">Token erstellen</flux:button>
            </form>

            @if ($plainTextToken)
                <flux:callout variant="success" icon="check" heading="Token erstellt – jetzt kopieren, es wird nur einmal angezeigt.">
                    <flux:callout.text>
                        <flux:input :value="$plainTextToken" readonly copyable class="font-mono" />
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if ($this->tokens->isNotEmpty())
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Zuletzt genutzt</flux:table.column>
                        <flux:table.column align="end"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->tokens as $token)
                            <flux:table.row :key="$token->id">
                                <flux:table.cell>{{ $token->name }}</flux:table.cell>
                                <flux:table.cell>{{ $token->last_used_at?->format('d.m.Y H:i') ?? 'nie' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="revokeToken({{ $token->id }})"
                                        wire:confirm="Token „{{ $token->name }}“ widerrufen?">Widerrufen</flux:button>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </div>
    </x-pages::settings.layout>
</section>
