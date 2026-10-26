<?php

use App\Actions\FoundReports\PurgeExpiredFoundReportContacts;
use App\Actions\FoundReports\ResolveFoundReport;
use App\Enums\FoundReportStatus;
use App\Models\FoundReport;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Fundmeldungen')] class extends Component {
    use WithPagination;

    #[Url(except: 'open')]
    public string $status = 'open';

    public function mount(): void
    {
        Gate::authorize('viewAny', FoundReport::class);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function resolve(int $reportId, ResolveFoundReport $resolve): void
    {
        $resolve->handle(Auth::user(), FoundReport::query()->findOrFail($reportId));

        Flux::toast(variant: 'success', text: 'Als erledigt markiert.');
    }

    #[Computed]
    public function reports(): LengthAwarePaginator
    {
        return FoundReport::query()
            ->with(['item', 'resolver'])
            ->when(FoundReportStatus::tryFrom($this->status), fn ($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">Fundmeldungen</flux:heading>
        <flux:text class="mt-1">
            Meldungen von der öffentlichen Statusseite. Kontaktdaten der Finder werden nach {{ PurgeExpiredFoundReportContacts::retentionDays() }} Tagen automatisch gelöscht.
        </flux:text>
    </div>

    <flux:radio.group wire:model.live="status" variant="segmented" class="max-w-sm">
        <flux:radio value="open" label="Offen" />
        <flux:radio value="resolved" label="Erledigt" />
        <flux:radio value="all" label="Alle" />
    </flux:radio.group>

    <flux:error name="report" />

    @forelse ($this->reports as $report)
        <flux:card wire:key="report-{{ $report->id }}" class="flex flex-col gap-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:link :href="route('items.show', $report->item)" wire:navigate class="font-medium">{{ $report->item->name }}</flux:link>
                    <flux:text class="mt-1">Eingegangen am {{ $report->created_at->format('d.m.Y \u\m H:i') }}</flux:text>
                </div>
                @if ($report->status === FoundReportStatus::Open)
                    <flux:button size="sm" icon="check" wire:click="resolve({{ $report->id }})">Erledigt</flux:button>
                @else
                    <flux:badge size="sm">erledigt {{ $report->resolved_at->format('d.m.Y') }}@if ($report->resolver) · {{ $report->resolver->name }}@endif</flux:badge>
                @endif
            </div>

            <dl class="grid gap-3 sm:grid-cols-3">
                <div>
                    <dt><flux:text>Fundort</flux:text></dt>
                    <dd class="mt-1">{{ $report->found_location }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt><flux:text>Nachricht</flux:text></dt>
                    <dd class="mt-1 whitespace-pre-line">{{ $report->message }}</dd>
                </div>
                <div class="sm:col-span-3">
                    <dt><flux:text>Kontakt</flux:text></dt>
                    <dd class="mt-1">
                        @if ($report->contact)
                            {{ $report->contact }}
                        @elseif ($report->contact_purged_at)
                            <span class="text-zinc-500">gelöscht am {{ $report->contact_purged_at->format('d.m.Y') }} (Löschfrist)</span>
                        @else
                            <span class="text-zinc-500">nicht angegeben</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </flux:card>
    @empty
        <flux:text>Keine Fundmeldungen.</flux:text>
    @endforelse

    {{ $this->reports->links() }}
</div>
