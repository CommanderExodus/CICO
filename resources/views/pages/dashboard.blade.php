<?php

use App\Enums\FoundReportStatus;
use App\Enums\LoanStatus;
use App\Models\FoundReport;
use App\Models\Item;
use App\Models\Loan;
use App\Queries\ListLoans;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Übersicht')] class extends Component {
    #[Computed]
    public function myLoans(): Collection
    {
        return app(ListLoans::class)->query(Auth::user(), ['mine' => true, 'status' => 'active'])->limit(10)->get();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function stats(): array
    {
        return [
            'items' => Item::query()->count(),
            'issued' => Loan::query()->where('status', LoanStatus::Issued)->count(),
            'overdue' => Loan::query()->overdue()->count(),
            'pickups' => Loan::query()->where('status', LoanStatus::Reserved)->whereDate('starts_on', '<=', today())->count(),
            'found_reports' => FoundReport::query()->where('status', FoundReportStatus::Open)->count(),
        ];
    }

    #[Computed]
    public function overdueLoans(): Collection
    {
        return Loan::query()->overdue()->with(['item', 'borrower'])->orderBy('ends_on')->limit(8)->get();
    }
}; ?>

<div class="flex flex-col gap-8">
    <div>
        <flux:heading size="xl" level="1">Hallo {{ auth()->user()->name }}</flux:heading>
        <flux:text class="mt-1">{{ config('cico.organisation') }} · {{ auth()->user()->role->label() }}</flux:text>
    </div>

    @if (auth()->user()->canManageInventory())
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <flux:card size="sm">
                <flux:text>Gegenstände</flux:text>
                <flux:heading size="xl" class="mt-1">{{ number_format($this->stats['items'], 0, ',', '.') }}</flux:heading>
            </flux:card>
            <flux:card size="sm">
                <flux:text>Ausgegeben</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $this->stats['issued'] }}</flux:heading>
            </flux:card>
            <flux:card size="sm">
                <flux:text>Überfällig</flux:text>
                <flux:heading size="xl" class="mt-1 {{ $this->stats['overdue'] > 0 ? 'text-red-600 dark:text-red-400' : '' }}">{{ $this->stats['overdue'] }}</flux:heading>
            </flux:card>
            <flux:card size="sm">
                <flux:text>Zur Abholung fällig</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $this->stats['pickups'] }}</flux:heading>
            </flux:card>
            <flux:card size="sm">
                <flux:text>Offene Fundmeldungen</flux:text>
                <flux:heading size="xl" class="mt-1">{{ $this->stats['found_reports'] }}</flux:heading>
            </flux:card>
        </div>

        @if ($this->overdueLoans->isNotEmpty())
            <section class="flex flex-col gap-3">
                <flux:heading size="lg">Überfällige Rückgaben</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Gegenstand</flux:table.column>
                        <flux:table.column>Ausleiher</flux:table.column>
                        <flux:table.column>Rückgabe fällig</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->overdueLoans as $loan)
                            <flux:table.row :key="$loan->id">
                                <flux:table.cell>
                                    <flux:link :href="route('items.show', $loan->item)" wire:navigate>{{ $loan->item->name }}</flux:link>
                                </flux:table.cell>
                                <flux:table.cell>{{ $loan->borrower->name }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge color="red" size="sm">seit {{ $loan->ends_on->format('d.m.Y') }}</flux:badge>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </section>
        @endif
    @endif

    <section class="flex flex-col gap-3">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">Meine aktuellen Ausleihen</flux:heading>
            <flux:button size="sm" variant="ghost" icon="magnifying-glass" :href="route('items.index')" wire:navigate>Gegenstand suchen</flux:button>
        </div>

        @if ($this->myLoans->isEmpty())
            <flux:text>Du hast gerade nichts reserviert oder ausgeliehen.</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Gegenstand</flux:table.column>
                    <flux:table.column>Zeitraum</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->myLoans as $loan)
                        <flux:table.row :key="$loan->id">
                            <flux:table.cell>
                                <flux:link :href="route('items.show', $loan->item)" wire:navigate>{{ $loan->item->name }}</flux:link>
                            </flux:table.cell>
                            <flux:table.cell>{{ $loan->starts_on->format('d.m.Y') }} – {{ $loan->ends_on->format('d.m.Y') }}</flux:table.cell>
                            <flux:table.cell>
                                <x-loan-status :loan="$loan" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </section>
</div>
