<?php

use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\SaveCategory;
use App\Models\Category;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kategorien')] class extends Component {
    public string $name = '';

    public ?int $editingId = null;

    public string $editingName = '';

    public function mount(): void
    {
        Gate::authorize('create', Category::class);
    }

    public function create(SaveCategory $save): void
    {
        $save->handle(Auth::user(), ['name' => $this->name]);

        $this->reset('name');
        Flux::toast(variant: 'success', text: 'Kategorie angelegt.');
    }

    public function edit(int $categoryId): void
    {
        $this->resetErrorBag();
        $this->editingId = $categoryId;
        $this->editingName = Category::query()->findOrFail($categoryId)->name;
    }

    public function rename(SaveCategory $save): void
    {
        // Die Action meldet Fehler unter „name“; das Inline-Feld heißt editingName.
        try {
            $save->handle(Auth::user(), ['name' => $this->editingName], Category::query()->findOrFail($this->editingId));
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(['editingName' => $e->errors()['name'] ?? $e->getMessage()]);
        }

        $this->reset('editingId', 'editingName');
        Flux::toast(variant: 'success', text: 'Kategorie umbenannt.');
    }

    public function delete(int $categoryId, DeleteCategory $delete): void
    {
        $delete->handle(Auth::user(), Category::query()->findOrFail($categoryId));

        Flux::toast(variant: 'success', text: 'Kategorie gelöscht.');
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::query()->withCount('items')->orderBy('name')->get();
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6">
    <flux:heading size="xl" level="1">Kategorien</flux:heading>

    <form wire:submit="create" class="flex items-start gap-2">
        <div class="flex-1">
            <flux:input wire:model="name" placeholder="Neue Kategorie" maxlength="80" required />
        </div>
        <flux:button type="submit" variant="primary" icon="plus">Anlegen</flux:button>
    </form>

    <flux:error name="category" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column align="end">Gegenstände</flux:table.column>
            <flux:table.column align="end"></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->categories as $category)
                <flux:table.row :key="$category->id">
                    <flux:table.cell>
                        @if ($editingId === $category->id)
                            <form wire:submit="rename" class="flex items-start gap-2">
                                <flux:input wire:model="editingName" size="sm" maxlength="80" required />
                                <flux:button type="submit" size="sm" variant="primary">Speichern</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="$set('editingId', null)">Abbrechen</flux:button>
                            </form>
                        @else
                            <flux:link :href="route('items.index', ['category' => $category->id])" wire:navigate>{{ $category->name }}</flux:link>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ $category->items_count }}</flux:table.cell>
                    <flux:table.cell align="end">
                        <div class="flex justify-end gap-1">
                            <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $category->id }})" />
                            <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $category->id }})"
                                wire:confirm="Kategorie „{{ $category->name }}“ löschen?" />
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
