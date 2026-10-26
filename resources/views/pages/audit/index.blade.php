<?php

use App\Enums\AuditChannel;
use App\Models\AuditLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Audit-Trail')] class extends Component {
    use WithPagination;

    public const TABLES = [
        'items' => 'Gegenstände',
        'loans' => 'Ausleihen',
        'locations' => 'Standorte',
        'categories' => 'Kategorien',
        'found_reports' => 'Fundmeldungen',
        'users' => 'Benutzer',
        'personal_access_tokens' => 'MCP-Tokens',
    ];

    #[Url(except: '')]
    public string $table = '';

    #[Url(except: '')]
    public string $channel = '';

    #[Url(except: '')]
    public string $record = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', AuditLog::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function entries(): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with('user')
            ->when($this->table === 'mcp', fn ($query) => $query->where('event', 'mcp.request'))
            ->when(array_key_exists($this->table, self::TABLES), fn ($query) => $query->where('table_name', $this->table))
            ->when(AuditChannel::tryFrom($this->channel), fn ($query, $channel) => $query->where('channel', $channel))
            ->when($this->record !== '', fn ($query) => $query->where('record_id', $this->record))
            ->orderByDesc('id')
            ->paginate(50);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">Audit-Trail</flux:heading>
        <flux:text class="mt-1">Jede Änderung mit altem und neuem Wert sowie alle MCP-Aufrufe. Die Einträge sind unveränderlich.</flux:text>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:select wire:model.live="table" class="max-w-52">
            <flux:select.option value="">Alle Bereiche</flux:select.option>
            @foreach (self::TABLES as $key => $label)
                <flux:select.option :value="$key">{{ $label }}</flux:select.option>
            @endforeach
            <flux:select.option value="mcp">MCP-Aufrufe</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="channel" class="max-w-52">
            <flux:select.option value="">Alle Kanäle</flux:select.option>
            @foreach (AuditChannel::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <div class="w-40">
            <flux:input wire:model.live.debounce.300ms="record" placeholder="Datensatz-ID" />
        </div>
    </div>

    <flux:table :paginate="$this->entries">
        <flux:table.columns>
            <flux:table.column>Zeitpunkt</flux:table.column>
            <flux:table.column>Ereignis</flux:table.column>
            <flux:table.column>Wer / Kanal</flux:table.column>
            <flux:table.column>Änderung</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->entries as $entry)
                <flux:table.row :key="$entry->id">
                    <flux:table.cell class="align-top">{{ $entry->occurred_at->timezone(config('app.timezone'))->format('d.m.Y H:i:s') }}</flux:table.cell>
                    <flux:table.cell class="align-top">
                        @if ($entry->event === 'mcp.request')
                            <flux:badge size="sm" color="violet">MCP {{ $entry->meta['method'] ?? '' }}</flux:badge>
                            @if (isset($entry->meta['tool']))
                                <div class="mt-1 font-mono text-xs">{{ $entry->meta['tool'] }}</div>
                            @endif
                        @else
                            <flux:badge size="sm" :color="match ($entry->event) { 'created' => 'green', 'deleted' => 'red', default => 'sky' }">
                                {{ match ($entry->event) { 'created' => 'angelegt', 'updated' => 'geändert', 'deleted' => 'gelöscht', default => $entry->event } }}
                            </flux:badge>
                            <div class="mt-1 text-xs">{{ self::TABLES[$entry->table_name] ?? $entry->table_name }} #{{ $entry->record_id }}</div>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="align-top">
                        <div>{{ $entry->user?->name ?? ($entry->user_id ? '#'.$entry->user_id : '–') }}</div>
                        <div class="text-xs text-zinc-500">
                            {{ $entry->channel->label() }}@if ($entry->client) · {{ $entry->client }}@endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell class="align-top whitespace-normal">
                        @if ($entry->event === 'mcp.request')
                            <span class="text-xs">
                                {{ ($entry->meta['outcome'] ?? 'ok') === 'ok' ? 'erfolgreich' : 'Fehler' }}
                                @if (! empty($entry->meta['arguments']))
                                    · <span class="font-mono">{{ json_encode($entry->meta['arguments'], JSON_UNESCAPED_UNICODE) }}</span>
                                @endif
                            </span>
                        @else
                            <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 font-mono text-xs">
                                @foreach (array_keys(($entry->new_values ?? []) + ($entry->old_values ?? [])) as $key)
                                    <dt class="text-zinc-500">{{ $key }}</dt>
                                    <dd class="break-all">
                                        @if ($entry->old_values !== null && array_key_exists($key, $entry->old_values))
                                            <span class="text-red-600 line-through dark:text-red-400">{{ json_encode($entry->old_values[$key], JSON_UNESCAPED_UNICODE) }}</span>
                                        @endif
                                        @if ($entry->new_values !== null && array_key_exists($key, $entry->new_values))
                                            <span class="text-green-700 dark:text-green-400">{{ json_encode($entry->new_values[$key], JSON_UNESCAPED_UNICODE) }}</span>
                                        @endif
                                    </dd>
                                @endforeach
                            </dl>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
