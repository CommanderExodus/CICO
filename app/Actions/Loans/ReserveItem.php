<?php

namespace App\Actions\Loans;

use App\Enums\LoanStatus;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Support\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Reserviert einen Einzelgegenstand für einen Zeitraum (R5).
 *
 * Ob der Zeitraum frei ist, entscheidet allein das Exclusion Constraint
 * loans_no_overlap (R6): Eine Vorab-Prüfung im Code wäre bei gleichzeitigen
 * Buchungen ohnehin nicht verlässlich.
 */
class ReserveItem
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input): Loan
    {
        $validated = Validator::make($input, [
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'borrower_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $borrower = isset($validated['borrower_id']) ? User::findOrFail($validated['borrower_id']) : $actor;

        Gate::forUser($actor)->authorize('create', [Loan::class, $borrower]);

        $item = Item::findOrFail($validated['item_id']);

        if (! $item->isUnit()) {
            throw ValidationException::withMessages([
                'item_id' => 'Mengenware kann noch nicht ausgeliehen werden.',
            ]);
        }

        $loan = new Loan([
            'item_id' => $item->id,
            'borrower_id' => $borrower->id,
            'starts_on' => $validated['starts_on'],
            'ends_on' => $validated['ends_on'],
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
        ]);
        $loan->status = LoanStatus::Reserved;

        try {
            DB::transaction(fn () => $loan->save());
        } catch (QueryException $e) {
            if (ConstraintViolation::is($e, 'loans_no_overlap')) {
                throw ValidationException::withMessages([
                    'starts_on' => 'Der Gegenstand ist im gewählten Zeitraum bereits reserviert oder ausgegeben.',
                ]);
            }

            throw $e;
        }

        return $loan->setRelation('item', $item)->setRelation('borrower', $borrower);
    }
}
