<?php

namespace App\Queries;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Ausleihen in der Sicht des Fragenden: Ausleiher sehen ausschließlich ihre
 * eigenen (R22), Verwalter und Admins alle. Mit mine = true auch für Verwalter
 * nur die eigenen, z. B. für das MCP-Tool „eigene Ausleihen“.
 */
class ListLoans
{
    public const FILTERS = ['active', 'reserved', 'issued', 'overdue', 'returned', 'cancelled', 'all'];

    /**
     * @param  array{status?: ?string, mine?: bool, search?: ?string, item_id?: ?int}  $filters
     * @return Builder<Loan>
     */
    public function query(User $actor, array $filters = []): Builder
    {
        Gate::forUser($actor)->authorize('viewAny', Loan::class);

        $query = Loan::query()->visibleTo($actor)->with(['item', 'borrower']);

        if ($filters['mine'] ?? false) {
            $query->where('borrower_id', $actor->id);
        }

        if (filled($filters['item_id'] ?? null)) {
            $query->where('item_id', (int) $filters['item_id']);
        }

        match ($filters['status'] ?? 'active') {
            'active' => $query->active(),
            'overdue' => $query->overdue(),
            'all' => null,
            default => $query->where('status', LoanStatus::from($filters['status'])),
        };

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(function (Builder $query) use ($like, $actor) {
                $query->whereHas('item', fn (Builder $items) => $items->where('name', 'ilike', $like));

                if ($actor->canManageInventory()) {
                    $query->orWhereHas('borrower', fn (Builder $users) => $users->where('name', 'ilike', $like));
                }
            });
        }

        return $query->orderByDesc('starts_on')->orderByDesc('id');
    }
}
