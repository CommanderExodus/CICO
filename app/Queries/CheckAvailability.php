<?php

namespace App\Queries;

use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * Prüft, ob ein Einzelgegenstand in einem Zeitraum frei ist.
 *
 * Belegte Zeiträume werden immer gezeigt, der Name des Ausleihers aber nur,
 * wenn der Fragende diese Ausleihe auch sehen darf (R22).
 */
class CheckAvailability
{
    /**
     * @return array{
     *     loanable: bool,
     *     available: bool,
     *     reason: ?string,
     *     conflicts: list<array{starts_on: string, ends_on: string, status: string, borrower: ?string}>,
     *     overdue_return_pending: bool
     * }
     */
    public function handle(User $actor, Item $item, CarbonInterface $from, CarbonInterface $to): array
    {
        Gate::forUser($actor)->authorize('view', $item);

        if (! $item->isUnit()) {
            return [
                'loanable' => false,
                'available' => false,
                'reason' => 'Mengenware kann noch nicht ausgeliehen werden.',
                'conflicts' => [],
                'overdue_return_pending' => false,
            ];
        }

        $conflicts = $item->loans()
            ->active()
            ->overlapping($from, $to)
            ->with('borrower')
            ->orderBy('starts_on')
            ->get()
            ->map(fn (Loan $loan) => [
                'starts_on' => $loan->starts_on->toDateString(),
                'ends_on' => $loan->ends_on->toDateString(),
                'status' => $loan->status->label(),
                'borrower' => Gate::forUser($actor)->allows('view', $loan) ? $loan->borrower->name : null,
            ])
            ->values()
            ->all();

        // Eine überfällige Ausleihe belegt den Zeitraum formal nicht mehr, der Gegenstand ist aber noch nicht zurück.
        $overdue = $item->loans()->overdue()->exists();

        return [
            'loanable' => true,
            'available' => $conflicts === [],
            'reason' => $conflicts === [] ? null : 'Im Zeitraum bereits reserviert oder ausgegeben.',
            'conflicts' => $conflicts,
            'overdue_return_pending' => $overdue,
        ];
    }
}
