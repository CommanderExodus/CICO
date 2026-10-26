<?php

namespace App\Actions\Locations;

use App\Models\Location;
use App\Models\User;
use App\Support\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Legt einen Standort an, benennt ihn um oder hängt ihn im Baum um (R2).
 * Ob ein Umhängen einen Zyklus erzeugen würde, prüft die Datenbank.
 */
class SaveLocation
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input, ?Location $location = null): Location
    {
        $location === null
            ? Gate::forUser($actor)->authorize('create', Location::class)
            : Gate::forUser($actor)->authorize('update', $location);

        $validated = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:40'],
            'parent_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
        ])->validate();

        $location ??= new Location;
        $location->fill([
            'name' => trim($validated['name']),
            'type' => filled($validated['type'] ?? null) ? trim($validated['type']) : null,
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        try {
            DB::transaction(fn () => $location->save());
        } catch (QueryException $e) {
            if (ConstraintViolation::is($e, 'locations_no_cycle', 'locations_not_own_parent')) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Ein Standort kann nicht unter sich selbst oder einem seiner Unterstandorte liegen.',
                ]);
            }

            if (ConstraintViolation::is($e, 'locations_name_unique_per_parent')) {
                throw ValidationException::withMessages([
                    'name' => 'An dieser Stelle gibt es bereits einen Standort mit diesem Namen.',
                ]);
            }

            throw $e;
        }

        return $location;
    }
}
