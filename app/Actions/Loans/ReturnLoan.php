<?php

namespace App\Actions\Loans;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ReturnLoan
{
    public function handle(User $actor, Loan $loan): Loan
    {
        Gate::forUser($actor)->authorize('return', $loan);

        $returned = DB::transaction(function () use ($loan) {
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($locked->status !== LoanStatus::Issued) {
                throw ValidationException::withMessages([
                    'loan' => 'Nur ausgegebene Gegenstände können zurückgebucht werden.',
                ]);
            }

            $locked->status = LoanStatus::Returned;
            $locked->returned_at = now();
            $locked->save();

            return $locked;
        });

        return $loan->setRawAttributes($returned->getAttributes(), true);
    }
}
