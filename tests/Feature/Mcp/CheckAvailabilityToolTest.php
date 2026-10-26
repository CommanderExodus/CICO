<?php

namespace Tests\Feature\Mcp;

use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R28: Lesendes Tool „Verfügbarkeit für Zeitraum prüfen“, mit denselben Rechten wie im Web (R27).
 */
class CheckAvailabilityToolTest extends TestCase
{
    use InteractsWithMcp;
    use RefreshDatabase;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = Item::factory()->create();
        Loan::factory()
            ->for($this->item)
            ->for(User::factory()->create(['name' => 'Bernd Borger']), 'borrower')
            ->between('2026-12-10', '2026-12-12')
            ->create();
    }

    public function test_a_free_period_is_reported_as_available(): void
    {
        $this->callTool(User::factory()->create(), 'check-availability', ['item_id' => $this->item->id, 'from' => '2026-12-01', 'to' => '2026-12-09'])
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.available', true)
            ->assertJsonPath('result.structuredContent.conflicts', []);
    }

    public function test_picking_up_on_the_return_day_of_another_loan_is_possible(): void
    {
        $this->callTool(User::factory()->create(), 'check-availability', ['item_id' => $this->item->id, 'from' => '2026-12-12', 'to' => '2026-12-14'])
            ->assertJsonPath('result.structuredContent.available', true);
    }

    public function test_a_conflict_is_reported_without_the_borrower_for_borrowers(): void
    {
        $this->callTool(User::factory()->borrower()->create(), 'check-availability', ['item_id' => $this->item->id, 'from' => '2026-12-11', 'to' => '2026-12-14'])
            ->assertJsonPath('result.structuredContent.available', false)
            ->assertJsonPath('result.structuredContent.conflicts.0.starts_on', '2026-12-10')
            ->assertJsonPath('result.structuredContent.conflicts.0.borrower', null)
            ->assertDontSee('Bernd Borger');
    }

    public function test_managers_see_who_booked_the_item(): void
    {
        $this->callTool(User::factory()->manager()->create(), 'check-availability', ['item_id' => $this->item->id, 'from' => '2026-12-11', 'to' => '2026-12-14'])
            ->assertJsonPath('result.structuredContent.conflicts.0.borrower', 'Bernd Borger');
    }

    public function test_bulk_items_are_reported_as_not_loanable(): void
    {
        $bulk = Item::factory()->bulk()->create();

        $this->callTool(User::factory()->create(), 'check-availability', ['item_id' => $bulk->id, 'from' => '2026-12-01', 'to' => '2026-12-02'])
            ->assertJsonPath('result.structuredContent.loanable', false);
    }

    public function test_invalid_periods_and_unknown_items_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->callTool($user, 'check-availability', ['item_id' => $this->item->id, 'from' => '2026-12-05', 'to' => '2026-12-01'])
            ->assertJsonPath('result.isError', true);

        $this->callTool($user, 'check-availability', ['item_id' => 999999, 'from' => '2026-12-01', 'to' => '2026-12-02'])
            ->assertJsonPath('result.isError', true);
    }
}
