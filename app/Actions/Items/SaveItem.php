<?php

namespace App\Actions\Items;

use App\Enums\ItemKind;
use App\Models\Item;
use App\Models\User;
use App\Support\ConstraintViolation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Legt einen Gegenstand an oder ändert ihn. Die Art (Einzelgegenstand/Mengenware)
 * wird beim Anlegen festgelegt und danach nicht mehr geändert.
 */
class SaveItem
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(User $actor, array $input, ?Item $item = null): Item
    {
        $item === null
            ? Gate::forUser($actor)->authorize('create', Item::class)
            : Gate::forUser($actor)->authorize('update', $item);

        $kind = $item?->kind ?? ItemKind::tryFrom((string) ($input['kind'] ?? ''));

        $rules = [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
        ];

        if ($item === null) {
            $rules['kind'] = ['required', Rule::enum(ItemKind::class)];
        }

        if ($kind === ItemKind::Unit) {
            $rules['serial_number'] = [
                'required', 'string', 'max:100',
                Rule::unique('items', 'serial_number')->where('kind', ItemKind::Unit->value)->ignore($item?->id),
            ];
        }

        if ($kind === ItemKind::Bulk) {
            $rules['quantity'] = ['required', 'integer', 'min:0', 'max:1000000'];
        }

        $validated = Validator::make($input, $rules)->validate();

        $item ??= new Item(['kind' => $kind]);
        $item->fill([
            'name' => trim($validated['name']),
            'description' => $validated['description'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'serial_number' => $kind === ItemKind::Unit ? trim($validated['serial_number']) : null,
            'quantity' => $kind === ItemKind::Bulk ? (int) $validated['quantity'] : null,
        ]);

        try {
            DB::transaction(fn () => $item->save());
        } catch (QueryException $e) {
            if (ConstraintViolation::is($e, 'items_serial_number_unique')) {
                throw ValidationException::withMessages([
                    'serial_number' => 'Diese Seriennummer ist bereits vergeben.',
                ]);
            }

            throw $e;
        }

        return $item;
    }
}
