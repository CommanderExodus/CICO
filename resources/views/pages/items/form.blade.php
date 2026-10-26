<?php

use App\Actions\Items\SaveItem;
use App\Enums\ItemKind;
use App\Models\Category;
use App\Models\Item;
use App\Support\LocationTree;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Gegenstand')] class extends Component {
    public ?Item $item = null;

    public string $kind = 'unit';
    public string $name = '';
    public string $description = '';
    public string $category_id = '';
    public string $location_id = '';
    public string $serial_number = '';
    public string $quantity = '';

    public function mount(?Item $item = null): void
    {
        if ($item?->exists) {
            Gate::authorize('update', $item);

            $this->item = $item;
            $this->kind = $item->kind->value;
            $this->name = $item->name;
            $this->description = (string) $item->description;
            $this->category_id = (string) $item->category_id;
            $this->location_id = (string) $item->location_id;
            $this->serial_number = (string) $item->serial_number;
            $this->quantity = (string) $item->quantity;

            return;
        }

        Gate::authorize('create', Item::class);
    }

    public function save(SaveItem $save): void
    {
        $item = $save->handle(Auth::user(), [
            'kind' => $this->kind,
            'name' => $this->name,
            'description' => $this->description,
            'category_id' => $this->category_id !== '' ? (int) $this->category_id : null,
            'location_id' => $this->location_id !== '' ? (int) $this->location_id : null,
            'serial_number' => $this->serial_number,
            'quantity' => $this->quantity,
        ], $this->item);

        Flux::toast(variant: 'success', text: $this->item ? 'Änderungen gespeichert.' : 'Gegenstand angelegt.');

        $this->redirectRoute('items.show', $item, navigate: true);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->orderBy('name')->get();
    }

    #[Computed]
    public function locationOptions(): array
    {
        return app(LocationTree::class)->options();
    }
}; ?>

<div class="flex max-w-2xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ $item ? 'Gegenstand bearbeiten' : 'Neuer Gegenstand' }}</flux:heading>
        @if ($item)
            <flux:text class="mt-1">{{ $item->name }}</flux:text>
        @endif
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        @if ($item)
            <flux:input :value="$item->kind->label()" label="Art" disabled description="Die Art lässt sich nach dem Anlegen nicht mehr ändern." />
        @else
            <flux:radio.group wire:model.live="kind" label="Art" variant="segmented">
                @foreach (ItemKind::cases() as $case)
                    <flux:radio :value="$case->value" :label="$case->label()" />
                @endforeach
            </flux:radio.group>
        @endif

        <flux:input wire:model="name" label="Bezeichnung" required maxlength="160" />

        @if ($kind === 'unit')
            <flux:input wire:model="serial_number" label="Seriennummer" required maxlength="100" description="Hersteller- oder Inventarnummer; muss eindeutig sein." />
        @else
            <flux:input wire:model="quantity" label="Menge" type="number" min="0" required />
        @endif

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:select wire:model="category_id" label="Kategorie">
                <flux:select.option value="">Keine</flux:select.option>
                @foreach ($this->categories as $category)
                    <flux:select.option :value="$category->id">{{ $category->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="location_id" label="Standort">
                <flux:select.option value="">Kein Standort</flux:select.option>
                @foreach ($this->locationOptions as $id => $path)
                    <flux:select.option :value="$id">{{ $path }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:textarea wire:model="description" label="Beschreibung" rows="4" />

        <div class="flex gap-2">
            <flux:button type="submit" variant="primary">Speichern</flux:button>
            <flux:button :href="$item ? route('items.show', $item) : route('items.index')" variant="ghost" wire:navigate>Abbrechen</flux:button>
        </div>
    </form>
</div>
