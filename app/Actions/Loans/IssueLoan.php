<?php

namespace App\Actions\Loans;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use App\Support\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Gibt einen reservierten Gegenstand aus. Bei vorzeitiger Ausgabe beginnt der
 * Zeitraum ab heute, damit die Belegung der Wirklichkeit entspricht.
 */
class IssueLoan
{
    public function handle(User $actor, Loan $loan): Loan
    {
        Gate::forUser($actor)->authorize('issue', $loan);

        try {
            $issued = DB::transaction(function () use ($loan) {
                $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);

                if ($locked->status !== LoanStatus::Reserved) {
                    throw ValidationException::withMessages([
                        'loan' => 'Nur reservierte Ausleihen können ausgegeben werden.',
                    ]);
                }

                $today = today();

                if ($locked->ends_on->lt($today)) {
                    throw ValidationException::withMessages([
                        'loan' => 'Der Zeitraum dieser Reservierung ist bereits abgelaufen.',
                    ]);
                }

                if ($locked->starts_on->gt($today)) {
                    $locked->starts_on = $today;
                }

                $locked->status = LoanStatus::Issued;
                $locked->issued_at = now();
                $locked->save();

                return $locked;
            });
        } catch (QueryException $e) {
            if (ConstraintViolation::is($e, 'loans_one_issued_per_item')) {
                throw ValidationException::withMessages([
                    'loan' => 'Der Gegenstand ist noch an jemand anderen ausgegeben und wurde nicht zurückgebucht.',
                ]);
            }

            if (ConstraintViolation::is($e, 'loans_no_overlap')) {
                throw ValidationException::withMessages([
                    'loan' => 'Der Gegenstand ist ab heute noch für jemand anderen reserviert.',
                ]);
            }

            throw $e;
        }

        return $loan->setRawAttributes($issued->getAttributes(), true);
    }
}
