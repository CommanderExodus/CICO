<?php

namespace Database\Factories;

use App\Enums\ItemKind;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * Standard: Einzelgegenstand mit Seriennummer.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => ItemKind::Unit,
            'name' => ucfirst(fake()->words(2, true)),
            'description' => null,
            'serial_number' => fake()->unique()->bothify('SN-####-????'),
            'quantity' => null,
        ];
    }

    public function bulk(int $quantity = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => ItemKind::Bulk,
            'serial_number' => null,
            'quantity' => $quantity,
        ]);
    }
}
