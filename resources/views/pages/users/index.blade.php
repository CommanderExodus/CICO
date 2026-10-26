<?php

use App\Actions\Users\ChangeUserRole;
use App\Enums\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Benutzer')] class extends Component {
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', User::class);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function changeRole(int $userId, string $role, ChangeUserRole $change): void
    {
        $user = User::query()->findOrFail($userId);

        $change->handle(Auth::user(), $user, Role::from($role));

        Flux::toast(variant: 'success', text: "{$user->name} ist jetzt {$user->role->label()}.");
    }

    #[Computed]
    public function users(): LengthAwarePaginator
    {
        $like = '%'.addcslashes(trim($this->search), '%_\\').'%';

        return User::query()
            ->when(trim($this->search) !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)))
            ->withCount(['loans as active_loans_count' => fn ($query) => $query->active()])
            ->orderBy('name')
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">Benutzer</flux:heading>
        <flux:text class="mt-1">Admins verwalten alles, Verwalter den Bestand und alle Ausleihen, Ausleiher sehen nur ihre eigenen Ausleihen.</flux:text>
    </div>

    <div class="max-w-md">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Name oder E-Mail" clearable />
    </div>

    <flux:error name="role" />

    <flux:table :paginate="$this->users">
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column class="max-md:hidden">E-Mail</flux:table.column>
            <flux:table.column align="end">Aktive Ausleihen</flux:table.column>
            <flux:table.column>Rolle</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($this->users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell>{{ $user->name }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ $user->email }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $user->active_loans_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:select size="sm" class="max-w-40" wire:change="changeRole({{ $user->id }}, $event.target.value)">
                            @foreach (Role::cases() as $role)
                                <flux:select.option :value="$role->value" :selected="$user->role === $role">{{ $role->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
