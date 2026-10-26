<?php

use App\Actions\Locations\DeleteLocation;
use App\Actions\Locations\SaveLocation;
use App\Models\Item;
use App\Models\Location;
use App\Support\LocationTree;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Standorte')] class extends Component {
    public string $name = '';
    public string $type = '';
    public string $parent_id = '';

    public ?int $editingId = null;

    public function mount(): void
    {
        Gate::authorize('create', Location::class);
    }

    public function create(): void
    {
        $this->resetErrorBag();
        $this->editingId = null;
        $this->reset('name', 'type', 'parent_id');

        Flux::modal('location-form')->show();
    }

    public function createChild(int $parentId): void
    {
        $this->create();
        $this->parent_id = (string) $parentId;
    }

    public function edit(int $locationId): void
    {
        $location = Location::query()->findOrFail($locationId);

        $this->resetErrorBag();
        $this->editingId = $location->id;
        $this->name = $location->name;
        $this->type = (string) $location->type;
        $this->parent_id = (string) $location->parent_id;

        Flux::modal('location-form')->show();
    }

    public function save(SaveLocation $save): void
    {
        $save->handle(Auth::user(), [
            'name' => $this->name,
            'type' => $this->type,
            'parent_id' => $this->parent_id !== '' ? (int) $this->parent_id : null,
        ], $this->editingId ? Location::query()->findOrFail($this->editingId) : null);

        Flux::modal('location-form')->close();
        Flux::toast(variant: 'success', text: 'Standort gespeichert.');

        $this->reset('name', 'type', 'parent_id', 'editingId');
        unset($this->tree);
    }

    public function delete(int $locationId, DeleteLocation $delete): void
    {
        $delete->handle(Auth::user(), Location::query()->findOrFail($locationId));

        Flux::toast(variant: 'success', text: 'Standort gelöscht.');
        unset($this->tree);
    }

    #[Computed]
    public function tree(): LocationTree
    {
        return new LocationTree;
    }

    /**
     * @return array<int, int>
     */
    #[Computed]
    public function itemCounts(): array
    {
        return Item::query()
            ->whereNotNull('location_id')
            ->groupBy('location_id')
            ->selectRaw('location_id, count(*) as aggregate')
            ->pluck('aggregate', 'location_id')
            ->all();
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Standorte</flux:heading>
            <flux:text class="mt-1">Beliebig tief verschachtelt, z. B. Gebäude › Raum › Regal › Fach.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">Neuer Standort</flux:button>
    </div>

    <flux:error name="location" />

    @if ($this->tree->flattened() === [])
        <flux:text>Noch keine Standorte angelegt.</flux:text>
    @else
        <flux:table>
            <flux:table.columns>
                <flux:table.column>Standort</flux:table.column>
                <flux:table.column>Typ</flux:table.column>
                <flux:table.column align="end">Gegenstände</flux:table.column>
                <flux:table.column align="end"></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach ($this->tree->flattened() as ['location' => $location, 'depth' => $depth])
                    <flux:table.row :key="$location->id">
                        <flux:table.cell>
                            <span style="padding-inline-start: {{ $depth * 1.5 }}rem" class="inline-flex items-center gap-2">
                                @if ($depth > 0)<span class="text-zinc-400">└</span>@endif
                                <flux:link :href="route('items.index', ['location' => $location->id])" wire:navigate>{{ $location->name }}</flux:link>
                            </span>
                        </flux:table.cell>
                        <flux:table.cell>{{ $location->type ?? '' }}</flux:table.cell>
                        <flux:table.cell align="end">{{ $this->itemCounts[$location->id] ?? 0 }}</flux:table.cell>
                        <flux:table.cell align="end">
                            <div class="flex justify-end gap-1">
                                <flux:button size="xs" variant="ghost" icon="plus" wire:click="createChild({{ $location->id }})" tooltip="Unterstandort anlegen" />
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $location->id }})" tooltip="Bearbeiten" />
                                <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $location->id }})"
                                    wire:confirm="Standort „{{ $location->name }}“ löschen?" tooltip="Löschen" />
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    @endif

    <flux:modal name="location-form" class="w-full max-w-lg">
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:heading size="lg">{{ $editingId ? 'Standort bearbeiten' : 'Neuer Standort' }}</flux:heading>

            <flux:input wire:model="name" label="Name" required maxlength="120" />
            <flux:input wire:model="type" label="Typ" maxlength="40" placeholder="z. B. Gebäude, Raum, Regal, Fach" />
            <flux:select wire:model="parent_id" label="Liegt in">
                <flux:select.option value="">– oberste Ebene –</flux:select.option>
                @foreach ($this->tree->options() as $id => $path)
                    @if ($id !== $editingId)
                        <flux:select.option :value="$id">{{ $path }}</flux:select.option>
                    @endif
                @endforeach
            </flux:select>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Abbrechen</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">Speichern</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
