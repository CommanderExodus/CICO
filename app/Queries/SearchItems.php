<?php

namespace App\Queries;

use App\Enums\ItemAvailability;
use App\Enums\ItemKind;
use App\Enums\LoanStatus;
use App\Models\Item;
use App\Models\User;
use App\Support\LocationTree;
use App\Support\PublicId;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * Durchsucht den Bestand nach Name, Seriennummer oder öffentlicher Kennung.
 * Ein Standortfilter schließt alle Unterstandorte ein.
 */
class SearchItems
{
    public function __construct(private LocationTree $locations)
    {
        //
    }

    /**
     * @param  array{search?: ?string, kind?: ?string, category_id?: ?int, location_id?: ?int, availability?: ?string}  $filters
     * @return LengthAwarePaginator<int, Item>
     */
    public function handle(User $actor, array $filters = [], int $perPage = 25, ?int $page = null): LengthAwarePaginator
    {
        Gate::forUser($actor)->authorize('viewAny', Item::class);

        return $this->query($filters)
            ->with(['category', 'currentLoan'])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage, page: $page);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Item>
     */
    public function query(array $filters): Builder
    {
        $query = Item::query();

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $publicId = PublicId::normalize($search);

            $query->where(function (Builder $query) use ($like, $publicId) {
                $query->where('name', 'ilike', $like)
                    ->orWhere('serial_number', 'ilike', $like);

                if (PublicId::isValid($publicId)) {
                    $query->orWhere('public_id', $publicId);
                }
            });
        }

        if (($kind = ItemKind::tryFrom((string) ($filters['kind'] ?? ''))) !== null) {
            $query->where('kind', $kind);
        }

        if (filled($filters['category_id'] ?? null)) {
            $query->where('category_id', (int) $filters['category_id']);
        }

        if (filled($filters['location_id'] ?? null)) {
            $query->whereIn('location_id', $this->locations->descendantIdsAndSelf((int) $filters['location_id']));
        }

        $issued = fn (Builder $loans) => $loans->where('status', LoanStatus::Issued);

        match (ItemAvailability::tryFrom((string) ($filters['availability'] ?? ''))) {
            ItemAvailability::Available => $query->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('kind', ItemKind::Unit)->whereDoesntHave('loans', $issued))
                ->orWhere(fn (Builder $query) => $query->where('kind', ItemKind::Bulk)->where('quantity', '>', 0))),
            ItemAvailability::OnLoan => $query->where('kind', ItemKind::Unit)->whereHas('loans', $issued),
            ItemAvailability::OutOfStock => $query->where('kind', ItemKind::Bulk)->where('quantity', 0),
            null => null,
        };

        return $query;
    }
}
