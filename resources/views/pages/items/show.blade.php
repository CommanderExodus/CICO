<?php

use App\Actions\Loans\CancelLoan;
use App\Actions\Loans\IssueLoan;
use App\Actions\Loans\ReserveItem;
use App\Actions\Loans\ReturnLoan;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Queries\CheckAvailability;
use App\Queries\ListLoans;
use App\Support\LocationTree;
use App\Support\PublicId;
use App\Support\QrCode;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gegenstand')] class extends Component {
    public Item $item;

    public string $starts_on = '';
    public string $ends_on = '';
    public string $notes = '';
    public string $borrower_id = '';
    public bool $issue_now = false;

    public string $check_from = '';
    public string $check_to = '';

    /** @var array<string, mixed>|null */
    public ?array $availabilityResult = null;

    public function mount(Item $item): void
    {
        Gate::authorize('view', $item);

        $this->item = $item;
        $this->starts_on = $this->check_from = today()->toDateString();
        $this->ends_on = $this->check_to = today()->addDays(2)->toDateString();
    }

    public function checkAvailability(CheckAvailability $availability): void
    {
        $this->validate([
            'check_from' => ['required', 'date'],
            'check_to' => ['required', 'date', 'after_or_equal:check_from'],
        ]);

        $this->availabilityResult = $availability->handle(
            Auth::user(),
            $this->item,
            Carbon::parse($this->check_from),
            Carbon::parse($this->check_to),
        );
    }

    public function reserve(ReserveItem $reserve, IssueLoan $issue): void
    {
        $actor = Auth::user();

        DB::transaction(function () use ($reserve, $issue, $actor) {
            $loan = $reserve->handle($actor, [
                'item_id' => $this->item->id,
                'borrower_id' => $actor->canManageInventory() && $this->borrower_id !== '' ? (int) $this->borrower_id : $actor->id,
                'starts_on' => $this->starts_on,
                'ends_on' => $this->ends_on,
                'notes' => $this->notes,
            ]);

            if ($this->issue_now && $actor->canManageInventory()) {
                $issue->handle($actor, $loan);
            }
        });

        $this->reset('notes', 'issue_now', 'availabilityResult');
        $this->item->unsetRelation('currentLoan');

        Flux::toast(variant: 'success', text: 'Ausleihe gespeichert.');
    }

    public function issue(int $loanId, IssueLoan $issue): void
    {
        $issue->handle(Auth::user(), $this->findLoan($loanId));
        $this->item->unsetRelation('currentLoan');

        Flux::toast(variant: 'success', text: 'Ausgegeben.');
    }

    public function returnLoan(int $loanId, ReturnLoan $return): void
    {
        $return->handle(Auth::user(), $this->findLoan($loanId));
        $this->item->unsetRelation('currentLoan');

        Flux::toast(variant: 'success', text: 'Rückgabe gebucht.');
    }

    public function cancel(int $loanId, CancelLoan $cancel): void
    {
        $cancel->handle(Auth::user(), $this->findLoan($loanId));

        Flux::toast(variant: 'success', text: 'Reservierung storniert.');
    }

    private function findLoan(int $loanId): Loan
    {
        return $this->item->loans()->findOrFail($loanId);
    }

    #[Computed]
    public function loans(): Collection
    {
        return app(ListLoans::class)
            ->query(Auth::user(), ['item_id' => $this->item->id, 'status' => 'all'])
            ->limit(20)
            ->get();
    }

    #[Computed]
    public function borrowers(): Collection
    {
        return User::query()->orderBy('name')->get(['id', 'name']);
    }

    #[Computed]
    public function foundReports(): Collection
    {
        return $this->item->foundReports()->latest()->limit(5)->get();
    }

    #[Computed]
    public function locationPath(): ?string
    {
        return app(LocationTree::class)->path($this->item->location_id);
    }

    #[Computed]
    public function qr(): string
    {
        return QrCode::svg($this->item->publicUrl(), 160);
    }
}; ?>

<div class="flex flex-col gap-8">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:text>{{ $item->kind->label() }}@if ($item->category) · {{ $item->category->name }}@endif</flux:text>
            <flux:heading size="xl" level="1" class="mt-1">{{ $item->name }}</flux:heading>
            <div class="mt-2">
                @php($availability = $item->availability())
                <flux:badge :color="$availability->color()">{{ ucfirst($availability->label()) }}</flux:badge>
            </div>
        </div>

        @can('update', $item)
            <div class="flex gap-2">
                <flux:button icon="printer" :href="route('items.label', $item)" target="_blank">Etikett</flux:button>
                <flux:button icon="pencil-square" :href="route('items.edit', $item)" wire:navigate>Bearbeiten</flux:button>
            </div>
        @endcan
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <flux:card class="lg:col-span-2">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt><flux:text>Standort</flux:text></dt>
                    <dd class="mt-1">{{ $this->locationPath ?? '–' }}</dd>
                </div>
                @if ($item->isUnit())
                    <div>
                        <dt><flux:text>Seriennummer</flux:text></dt>
                        <dd class="mt-1 font-mono">{{ $item->serial_number }}</dd>
                    </div>
                @else
                    <div>
                        <dt><flux:text>Menge</flux:text></dt>
                        <dd class="mt-1">{{ $item->quantity }}</dd>
                    </div>
                @endif
                <div class="sm:col-span-2">
                    <dt><flux:text>Beschreibung</flux:text></dt>
                    <dd class="mt-1 whitespace-pre-line">{{ $item->description ?: '–' }}</dd>
                </div>
            </dl>
        </flux:card>

        <flux:card class="flex items-center gap-4">
            <div class="w-28 shrink-0 rounded bg-white p-1 [&>svg]:h-auto [&>svg]:w-full">{!! $this->qr !!}</div>
            <div class="min-w-0">
                <flux:text>Öffentliche Statusseite</flux:text>
                <p class="mt-1 font-mono text-sm">{{ PublicId::format($item->public_id) }}</p>
                <flux:link :href="$item->publicUrl()" target="_blank" class="mt-1 text-sm">Seite öffnen</flux:link>
            </div>
        </flux:card>
    </div>

    @if ($item->isUnit())
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">Reservieren</flux:heading>

                <form wire:submit="reserve" class="flex flex-col gap-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="starts_on" type="date" label="Abholung am" required />
                        <flux:input wire:model="ends_on" type="date" label="Rückgabe am" description="Am Rückgabetag darf schon der Nächste abholen." required />
                    </div>

                    @if (auth()->user()->canManageInventory())
                        <flux:select wire:model="borrower_id" label="Ausleiher">
                            <flux:select.option value="">Ich selbst</flux:select.option>
                            @foreach ($this->borrowers as $borrower)
                                <flux:select.option :value="$borrower->id">{{ $borrower->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif

                    <flux:input wire:model="notes" label="Notiz" maxlength="1000" />

                    @if (auth()->user()->canManageInventory())
                        <flux:checkbox wire:model="issue_now" label="Sofort ausgeben" description="Für die Ausgabe direkt vor Ort; der Zeitraum beginnt dann heute." />
                    @endif

                    <flux:error name="item_id" />

                    <div>
                        <flux:button type="submit" variant="primary" icon="calendar">Reservieren</flux:button>
                    </div>
                </form>
            </flux:card>

            <flux:card class="flex flex-col gap-4">
                <flux:heading size="lg">Verfügbarkeit prüfen</flux:heading>

                <form wire:submit="checkAvailability" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                    <flux:input wire:model="check_from" type="date" label="Abholung" required />
                    <flux:input wire:model="check_to" type="date" label="Rückgabe" required />
                    <flux:button type="submit">Prüfen</flux:button>
                </form>

                @if ($availabilityResult)
                    @if ($availabilityResult['available'])
                        <flux:callout variant="success" icon="check" heading="Im Zeitraum frei" />
                    @else
                        <flux:callout variant="warning" icon="exclamation-triangle" :heading="$availabilityResult['reason']">
                            <flux:callout.text>
                                <ul class="list-disc ps-4">
                                    @foreach ($availabilityResult['conflicts'] as $conflict)
                                        <li>
                                            {{ \Illuminate\Support\Carbon::parse($conflict['starts_on'])->format('d.m.Y') }} –
                                            {{ \Illuminate\Support\Carbon::parse($conflict['ends_on'])->format('d.m.Y') }}
                                            ({{ $conflict['status'] }}{{ $conflict['borrower'] ? ', '.$conflict['borrower'] : '' }})
                                        </li>
                                    @endforeach
                                </ul>
                            </flux:callout.text>
                        </flux:callout>
                    @endif

                    @if ($availabilityResult['overdue_return_pending'])
                        <flux:callout variant="danger" icon="exclamation-triangle" heading="Eine überfällige Rückgabe steht noch aus." />
                    @endif
                @endif
            </flux:card>
        </div>
    @else
        <flux:callout icon="cube" heading="Mengenware">
            <flux:callout.text>Ausleihen von Mengenware mit Mengenprüfung folgen in einer späteren Ausbaustufe (R7).</flux:callout.text>
        </flux:callout>
    @endif

    <section class="flex flex-col gap-3">
        <flux:heading size="lg">{{ auth()->user()->canManageInventory() ? 'Ausleihen' : 'Meine Ausleihen dieses Gegenstands' }}</flux:heading>

        <flux:error name="loan" />

        @if ($this->loans->isEmpty())
            <flux:text>Keine Ausleihen.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Zeitraum</flux:table.column>
                    @if (auth()->user()->canManageInventory())
                        <flux:table.column>Ausleiher</flux:table.column>
                    @endif
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column class="max-md:hidden">Notiz</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->loans as $loan)
                        <flux:table.row :key="$loan->id">
                            <flux:table.cell>{{ $loan->starts_on->format('d.m.Y') }} – {{ $loan->ends_on->format('d.m.Y') }}</flux:table.cell>
                            @if (auth()->user()->canManageInventory())
                                <flux:table.cell>{{ $loan->borrower->name }}</flux:table.cell>
                            @endif
                            <flux:table.cell><x-loan-status :loan="$loan" /></flux:table.cell>
                            <flux:table.cell class="max-w-64 truncate max-md:hidden">{{ $loan->notes }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <x-loan-actions :loan="$loan" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>

    @can('viewAny', \App\Models\FoundReport::class)
        @if ($this->foundReports->isNotEmpty())
            <section class="flex flex-col gap-3">
                <flux:heading size="lg">Fundmeldungen</flux:heading>
                @foreach ($this->foundReports as $report)
                    <flux:callout :variant="$report->status->value === 'open' ? 'warning' : 'secondary'" icon="hand-raised"
                        :heading="'Gefunden: '.$report->found_location.' · '.$report->created_at->format('d.m.Y H:i')">
                        <flux:callout.text>{{ $report->message }}</flux:callout.text>
                    </flux:callout>
                @endforeach
                <div>
                    <flux:link :href="route('found-reports.index')" wire:navigate>Alle Fundmeldungen</flux:link>
                </div>
            </section>
        @endif
    @endcan
</div>
