<?php

namespace Database\Factories;

use App\Enums\LoanStatus;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Standard: Reservierung ab morgen für drei Tage.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'borrower_id' => User::factory(),
            'starts_on' => today()->addDay(),
            'ends_on' => today()->addDays(3),
            'status' => LoanStatus::Reserved,
        ];
    }

    public function between(string $startsOn, string $endsOn): static
    {
        return $this->state(fn (array $attributes) => ['starts_on' => $startsOn, 'ends_on' => $endsOn]);
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => today()->subDay(),
            'ends_on' => today()->addDays(2),
            'status' => LoanStatus::Issued,
            'issued_at' => now()->subDay(),
        ]);
    }

    /**
     * Ausgegeben, Rückgabedatum vorbei – „überfällig“ ergibt sich daraus (R5).
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_on' => today()->subDays(10),
            'ends_on' => today()->subDays(3),
            'status' => LoanStatus::Issued,
            'issued_at' => now()->subDays(10),
        ]);
    }
}
