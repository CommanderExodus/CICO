<?php

namespace App\Actions\Loans;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Storniert eine Reservierung. Ausleihen werden nie gelöscht (Trigger loans_guard),
 * damit die Historie vollständig bleibt.
 */
class CancelLoan
{
    public function handle(User $actor, Loan $loan): Loan
    {
        Gate::forUser($actor)->authorize('cancel', $loan);

        $cancelled = DB::transaction(function () use ($loan) {
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($locked->status !== LoanStatus::Reserved) {
                throw ValidationException::withMessages([
                    'loan' => 'Nur Reservierungen können storniert werden.',
                ]);
            }

            $locked->status = LoanStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->save();

            return $locked;
        });

        return $loan->setRawAttributes($cancelled->getAttributes(), true);
    }
}
