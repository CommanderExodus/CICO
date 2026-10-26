<?php

use App\Actions\Loans\CancelLoan;
use App\Actions\Loans\IssueLoan;
use App\Actions\Loans\ReturnLoan;
use App\Models\Loan;
use App\Queries\ListLoans;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Ausleihen')] class extends Component {
    use WithPagination;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: false)]
    public bool $mine = false;

    public function updated(): void
    {
        $this->resetPage();
    }

    public function issue(int $loanId, IssueLoan $issue): void
    {
        $issue->handle(Auth::user(), $this->findLoan($loanId));

        Flux::toast(variant: 'success', text: 'Ausgegeben.');
    }

    public function returnLoan(int $loanId, ReturnLoan $return): void
    {
        $return->handle(Auth::user(), $this->findLoan($loanId));

        Flux::toast(variant: 'success', text: 'Rückgabe gebucht.');
    }

    public function cancel(int $loanId, CancelLoan $cancel): void
    {
        $cancel->handle(Auth::user(), $this->findLoan($loanId));

        Flux::toast(variant: 'success', text: 'Reservierung storniert.');
    }

    /**
     * Auch hier gilt die Sichtbarkeit aus R22: Fremde Ausleihen sind für Ausleiher nicht auffindbar.
     */
    private function findLoan(int $loanId): Loan
    {
        return Loan::query()->visibleTo(Auth::user())->findOrFail($loanId);
    }

    #[Computed]
    public function loans(): LengthAwarePaginator
    {
        return app(ListLoans::class)->query(Auth::user(), [
            'status' => in_array($this->status, ListLoans::FILTERS, true) ? $this->status : 'active',
            'search' => $this->search,
            'mine' => $this->mine,
        ])->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ auth()->user()->canManageInventory() ? 'Ausleihen' : 'Meine Ausleihen' }}</flux:heading>
        <flux:text class="mt-1">„Überfällig“ heißt: ausgegeben und Rückgabedatum überschritten.</flux:text>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <flux:select wire:model.live="status" class="max-w-52">
            <flux:select.option value="active">Aktiv (reserviert/ausgegeben)</flux:select.option>
            <flux:select.option value="reserved">Reserviert</flux:select.option>
            <flux:select.option value="issued">Ausgegeben</flux:select.option>
            <flux:select.option value="overdue">Überfällig</flux:select.option>
            <flux:select.option value="returned">Zurück</flux:select.option>
            <flux:select.option value="cancelled">Storniert</flux:select.option>
            <flux:select.option value="all">Alle</flux:select.option>
        </flux:select>
        <div class="min-w-64 flex-1">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
                :placeholder="auth()->user()->canManageInventory() ? 'Gegenstand oder Ausleiher' : 'Gegenstand'" clearable />
        </div>
        @if (auth()->user()->canManageInventory())
            <flux:switch wire:model.live="mine" label="Nur meine" />
        @endif
    </div>

    <flux:error name="loan" />

    @if ($this->loans->isEmpty())
        <flux:text>Keine Ausleihen gefunden.</flux:text>
    @else
        <flux:table :paginate="$this->loans">
            <flux:table.columns>
                <flux:table.column>Gegenstand</flux:table.column>
                @if (auth()->user()->canManageInventory())
                    <flux:table.column>Ausleiher</flux:table.column>
                @endif
                <flux:table.column>Zeitraum</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->loans as $loan)
                    <flux:table.row :key="$loan->id">
                        <flux:table.cell class="max-w-72 truncate">
                            <flux:link :href="route('items.show', $loan->item)" wire:navigate>{{ $loan->item->name }}</flux:link>
                        </flux:table.cell>
                        @if (auth()->user()->canManageInventory())
                            <flux:table.cell>{{ $loan->borrower->name }}</flux:table.cell>
                        @endif
                        <flux:table.cell>{{ $loan->starts_on->format('d.m.Y') }} – {{ $loan->ends_on->format('d.m.Y') }}</flux:table.cell>
                        <flux:table.cell><x-loan-status :loan="$loan" /></flux:table.cell>
                        <flux:table.cell align="end"><x-loan-actions :loan="$loan" /></flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
