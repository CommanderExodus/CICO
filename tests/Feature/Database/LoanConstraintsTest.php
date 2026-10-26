<?php

namespace Tests\Feature\Database;

use App\Enums\LoanStatus;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R5, R6, R36: Zeitraum, Status und Überschneidungsfreiheit von Ausleihen
 * werden in der Datenbank erzwungen.
 */
class LoanConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private Item $item;

    private User $borrower;

    protected function setUp(): void
    {
        parent::setUp();

        $this->item = Item::factory()->create();
        $this->borrower = User::factory()->create();
    }

    private function loan(string $from, string $to, LoanStatus $status = LoanStatus::Reserved, ?Item $item = null): Loan
    {
        return Loan::factory()->for($item ?? $this->item)->for($this->borrower, 'borrower')->between($from, $to)->create([
            'status' => $status,
            'issued_at' => $status === LoanStatus::Issued ? now() : null,
        ]);
    }

    public function test_overlapping_loans_of_the_same_item_are_rejected(): void
    {
        $this->loan('2026-11-01', '2026-11-05');

        $this->assertConstraintViolation('loans_no_overlap', fn () => $this->loan('2026-11-04', '2026-11-08'));
        $this->assertConstraintViolation('loans_no_overlap', fn () => $this->loan('2026-10-30', '2026-11-02'));
        $this->assertConstraintViolation('loans_no_overlap', fn () => $this->loan('2026-11-02', '2026-11-03'));
    }

    public function test_the_next_borrower_may_pick_up_on_the_return_day(): void
    {
        $this->loan('2026-11-01', '2026-11-05');
        $this->loan('2026-11-05', '2026-11-08');
        $this->loan('2026-10-28', '2026-11-01');

        $this->assertSame(3, Loan::query()->count());
    }

    public function test_a_single_day_loan_blocks_exactly_that_day(): void
    {
        $this->loan('2026-11-01', '2026-11-05');

        // Rückgabe am 5. morgens, Tagesleihe am 5. danach: erlaubt.
        $this->loan('2026-11-05', '2026-11-05');

        // Am 5. ist nun belegt – weder eine zweite Tagesleihe noch eine Leihe ab dem 5. passt.
        $this->assertConstraintViolation('loans_no_overlap', fn () => $this->loan('2026-11-05', '2026-11-05'));
        $this->assertConstraintViolation('loans_no_overlap', fn () => $this->loan('2026-11-05', '2026-11-07'));

        // Eine Tagesleihe mitten in einer laufenden Leihe kollidiert ebenfalls.
        $this->assertConstraintViolation('loans_no_overlap', fn () => $this->loan('2026-11-03', '2026-11-03'));

        // Ab dem Folgetag ist der Gegenstand wieder frei.
        $this->loan('2026-11-06', '2026-11-07');
    }

    public function test_adjacent_periods_and_other_items_do_not_conflict(): void
    {
        $this->loan('2026-11-01', '2026-11-05');
        $this->loan('2026-11-06', '2026-11-08');
        $this->loan('2026-11-01', '2026-11-05', item: Item::factory()->create());

        $this->assertSame(3, Loan::query()->count());
    }

    public function test_cancelled_and_returned_loans_no_longer_block_the_period(): void
    {
        $cancelled = $this->loan('2026-11-01', '2026-11-05');
        $cancelled->forceFill(['status' => LoanStatus::Cancelled, 'cancelled_at' => now()])->save();

        $returned = $this->loan('2026-11-01', '2026-11-05', LoanStatus::Issued);
        $returned->forceFill(['status' => LoanStatus::Returned, 'returned_at' => now()])->save();

        $this->loan('2026-11-03', '2026-11-04');

        $this->assertSame(1, Loan::query()->active()->count());
    }

    public function test_the_period_must_not_end_before_it_starts(): void
    {
        $this->assertConstraintViolation('loans_period_valid', fn () => $this->loan('2026-11-05', '2026-11-04'));
    }

    public function test_bulk_items_cannot_be_loaned_yet(): void
    {
        $bulk = Item::factory()->bulk()->create();

        // Ohne item_kind greift der zusammengesetzte Fremdschlüssel, mit 'bulk' der Check.
        $this->assertConstraintViolation('loans_item_fk', fn () => $this->loan('2026-11-01', '2026-11-02', item: $bulk));
        $this->assertConstraintViolation('loans_unit_items_only', fn () => DB::table('loans')->insert([
            'item_id' => $bulk->id, 'item_kind' => 'bulk', 'borrower_id' => $this->borrower->id,
            'starts_on' => '2026-11-01', 'ends_on' => '2026-11-02',
        ]));
    }

    public function test_new_loans_must_start_as_reserved_or_issued(): void
    {
        $this->assertConstraintViolation('loans_status_transition', fn () => DB::table('loans')->insert([
            'item_id' => $this->item->id, 'borrower_id' => $this->borrower->id,
            'starts_on' => '2026-11-01', 'ends_on' => '2026-11-02', 'status' => 'returned', 'returned_at' => now(), 'issued_at' => now(),
        ]));
    }

    public function test_only_valid_status_transitions_are_allowed(): void
    {
        $loan = $this->loan('2026-11-01', '2026-11-02');

        $this->assertConstraintViolation('loans_status_transition', fn () => $loan->forceFill([
            'status' => LoanStatus::Returned, 'issued_at' => now(), 'returned_at' => now(),
        ])->save());
    }

    public function test_closed_loans_are_immutable(): void
    {
        $loan = $this->loan('2026-11-01', '2026-11-02');
        $loan->forceFill(['status' => LoanStatus::Cancelled, 'cancelled_at' => now()])->save();

        $this->assertConstraintViolation('loans_closed_immutable', fn () => $loan->refresh()->forceFill(['notes' => 'nachträglich'])->save());
    }

    public function test_status_timestamps_must_match_the_status(): void
    {
        $this->assertConstraintViolation('loans_issued_at_matches_status', fn () => $this->loan('2026-11-01', '2026-11-02')
            ->forceFill(['status' => LoanStatus::Issued])->save());
    }

    public function test_loans_cannot_be_deleted(): void
    {
        $loan = $this->loan('2026-11-01', '2026-11-02');

        $this->assertConstraintViolation('loans_no_delete', fn () => $loan->delete());
    }

    public function test_an_item_can_only_be_issued_once_at_a_time_even_if_overdue(): void
    {
        $this->loan(today()->subDays(10)->toDateString(), today()->subDays(5)->toDateString(), LoanStatus::Issued);

        $this->assertConstraintViolation('loans_one_issued_per_item', fn () => $this->loan(
            today()->toDateString(), today()->addDay()->toDateString(), LoanStatus::Issued,
        ));
    }

    public function test_overdue_is_derived_not_stored(): void
    {
        $overdue = Loan::factory()->overdue()->for($this->item)->create();
        Loan::factory()->issued()->create();

        $this->assertSame(LoanStatus::Issued, $overdue->refresh()->status);
        $this->assertTrue($overdue->isOverdue());
        $this->assertSame([$overdue->id], Loan::query()->overdue()->pluck('id')->all());
    }
}
