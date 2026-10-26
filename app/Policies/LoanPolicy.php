<?php

namespace App\Policies;

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;

/**
 * Rechte rund um Ausleihen (R21, R22). Ausleiher handeln nur für sich selbst.
 */
class LoanPolicy
{
    /**
     * Die Liste selbst ist für alle offen; was darin steht, begrenzt Loan::scopeVisibleTo().
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Loan $loan): bool
    {
        return $user->canManageInventory() || $loan->borrower_id === $user->id;
    }

    /**
     * Ausleiher reservieren nur für sich selbst, Verwalter für alle.
     */
    public function create(User $user, User $borrower): bool
    {
        return $user->canManageInventory() || $borrower->id === $user->id;
    }

    /**
     * Ausgabe und Rückgabe bucht nur, wer den Bestand verwaltet.
     */
    public function issue(User $user, Loan $loan): bool
    {
        return $user->canManageInventory();
    }

    public function return(User $user, Loan $loan): bool
    {
        return $user->canManageInventory();
    }

    /**
     * Eigene Reservierungen dürfen Ausleiher stornieren, solange nichts ausgegeben ist.
     */
    public function cancel(User $user, Loan $loan): bool
    {
        return $user->canManageInventory()
            || ($loan->borrower_id === $user->id && $loan->status === LoanStatus::Reserved);
    }
}
