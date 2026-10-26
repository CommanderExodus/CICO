<?php

use App\Enums\ItemAvailability;
use App\Enums\ItemKind;
use App\Models\Category;
use App\Models\Item;
use App\Queries\SearchItems;
use App\Support\LocationTree;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Gegenstände')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $kind = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $location = '';

    #[Url(except: '')]
    public string $availability = '';

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'kind', 'category', 'location', 'availability');
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return app(SearchItems::class)->handle(Auth::user(), [
            'search' => $this->search,
            'kind' => $this->kind,
            'category_id' => $this->category !== '' ? (int) $this->category : null,
            'location_id' => $this->location !== '' ? (int) $this->location : null,
            'availability' => $this->availability,
        ]);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get();
    }

    #[Computed]
    public function locations(): LocationTree
    {
        return app(LocationTree::class);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Gegenstände</flux:heading>
            <flux:text class="mt-1">{{ number_format($this->items->total(), 0, ',', '.') }} Treffer</flux:text>
        </div>

        @can('create', Item::class)
            <flux:button variant="primary" icon="plus" :href="route('items.create')" wire:navigate>Neuer Gegenstand</flux:button>
        @endcan
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
        <div class="xl:col-span-2">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Name, Seriennummer oder Kennung" clearable />
        </div>
        <flux:select wire:model.live="kind">
            <flux:select.option value="">Alle Arten</flux:select.option>
            @foreach (ItemKind::cases() as $case)
                <flux:select.option :value="$case->value">{{ $case->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="category">
            <flux:select.option value="">Alle Kategorien</flux:select.option>
            @foreach ($this->categories as $category)
                <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="location">
            <flux:select.option value="">Alle Standorte</flux:select.option>
            @foreach ($this->locations->options() as $id => $path)
                <flux:select.option :value="$id">{{ $path }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="availability">
            <flux:select.option value="">Jeder Zustand</flux:select.option>
            @foreach (ItemAvailability::cases() as $case)
                <flux:select.option :value="$case->value">{{ ucfirst($case->label()) }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if ($this->items->isEmpty())
        <flux:callout icon="magnifying-glass" heading="Keine Gegenstände gefunden">
            <x-slot name="actions">
                <flux:button size="sm" wire:click="resetFilters">Filter zurücksetzen</flux:button>
            </x-slot>
        </flux:callout>
    @else
        <flux:table :paginate="$this->items">
            <flux:table.columns>
                <flux:table.column>Name</flux:table.column>
                <flux:table.column>Art</flux:table.column>
                <flux:table.column class="max-lg:hidden">Kategorie</flux:table.column>
                <flux:table.column class="max-md:hidden">Standort</flux:table.column>
                <flux:table.column>Zustand</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->items as $item)
                    <flux:table.row :key="$item->id">
                        <flux:table.cell class="max-w-72 truncate">
                            <flux:link :href="route('items.show', $item)" wire:navigate>{{ $item->name }}</flux:link>
                            @if ($item->serial_number)
                                <span class="ms-1 text-xs text-zinc-500">{{ $item->serial_number }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            {{ $item->kind->label() }}@if ($item->quantity !== null) · {{ $item->quantity }}@endif
                        </flux:table.cell>
                        <flux:table.cell class="max-lg:hidden">{{ $item->category?->name ?? '–' }}</flux:table.cell>
                        <flux:table.cell class="max-w-80 truncate max-md:hidden">{{ $this->locations->path($item->location_id) ?? '–' }}</flux:table.cell>
                        <flux:table.cell>
                            @php($availability = $item->availability())
                            <flux:badge size="sm" :color="$availability->color()">{{ $availability->label() }}</flux:badge>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif
</div>
