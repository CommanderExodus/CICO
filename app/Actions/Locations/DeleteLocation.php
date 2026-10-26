<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\User;
use App\Support\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteLocation
{
    public function handle(User $actor, Location $location): void
    {
        Gate::forUser($actor)->authorize('delete', $location);

        try {
            DB::transaction(fn () => $location->delete());
        } catch (QueryException $e) {
            if (ConstraintViolation::isForeignKey($e)) {
                throw ValidationException::withMessages([
                    'location' => "„{$location->name}“ enthält noch Unterstandorte oder Gegenstände und kann nicht gelöscht werden.",
                ]);
            }

            throw $e;
        }
    }
}
