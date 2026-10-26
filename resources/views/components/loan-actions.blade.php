@props(['loan'])

{{-- Aktionen rufen issue/returnLoan/cancel der umgebenden Livewire-Komponente auf. --}}
<div class="flex justify-end gap-1">
    @if ($loan->status === \App\Enums\LoanStatus::Reserved)
        @can('issue', $loan)
            <flux:button size="xs" icon="check" wire:click="issue({{ $loan->id }})">Ausgeben</flux:button>
        @endcan
        @can('cancel', $loan)
            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="cancel({{ $loan->id }})" wire:confirm="Reservierung wirklich stornieren?">Stornieren</flux:button>
        @endcan
    @elseif ($loan->status === \App\Enums\LoanStatus::Issued)
        @can('return', $loan)
            <flux:button size="xs" icon="arrow-uturn-left" wire:click="returnLoan({{ $loan->id }})">Zurückbuchen</flux:button>
        @endcan
    @endif
</div>
