<?php

namespace Database\Factories;

use App\Models\FoundReport;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FoundReport>
 */
class FoundReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'found_location' => fake()->streetName(),
            'message' => fake()->sentence(),
            'contact' => fake()->safeEmail(),
        ];
    }
}
