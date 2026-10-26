<?php

namespace Tests\Feature\Loans;

use App\Actions\Loans\CancelLoan;
use App\Actions\Loans\IssueLoan;
use App\Actions\Loans\ReserveItem;
use App\Actions\Loans\ReturnLoan;
use App\Enums\LoanStatus;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * R5, R6, R21: Ausleihen über die Service-Schicht, die Web und MCP gemeinsam nutzen.
 */
class LoanActionsTest extends TestCase
{
    use RefreshDatabase;

    private function reserve(User $actor, Item $item, string $from, string $to, ?User $borrower = null): Loan
    {
        return app(ReserveItem::class)->handle($actor, [
            'item_id' => $item->id,
            'borrower_id' => $borrower?->id,
            'starts_on' => $from,
            'ends_on' => $to,
        ]);
    }

    private function assertValidationError(string $key, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Erwartet wurde ein Validierungsfehler für [{$key}].");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }

    public function test_a_borrower_can_reserve_an_item_for_themselves(): void
    {
        $borrower = User::factory()->borrower()->create();
        $item = Item::factory()->create();

        $loan = $this->reserve($borrower, $item, today()->addDay()->toDateString(), today()->addDays(3)->toDateString());

        $this->assertSame(LoanStatus::Reserved, $loan->status);
        $this->assertSame($borrower->id, $loan->borrower_id);
    }

    public function test_a_borrower_cannot_reserve_for_someone_else(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->expectException(AuthorizationException::class);

        $this->reserve($borrower, Item::factory()->create(), today()->toDateString(), today()->toDateString(), User::factory()->create());
    }

    public function test_a_manager_can_reserve_for_others(): void
    {
        $manager = User::factory()->manager()->create();
        $borrower = User::factory()->create();

        $loan = $this->reserve($manager, Item::factory()->create(), today()->toDateString(), today()->toDateString(), $borrower);

        $this->assertSame($borrower->id, $loan->borrower_id);
    }

    public function test_an_overlapping_reservation_is_rejected_with_a_readable_error(): void
    {
        $item = Item::factory()->create();
        $this->reserve(User::factory()->create(), $item, today()->addDays(2)->toDateString(), today()->addDays(5)->toDateString());

        $this->assertValidationError('starts_on', fn () => $this->reserve(
            User::factory()->create(), $item, today()->addDays(4)->toDateString(), today()->addDays(8)->toDateString(),
        ));

        $this->assertSame(1, Loan::query()->count());
    }

    public function test_reservations_cannot_start_in_the_past_or_end_before_they_start(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create();

        $this->assertValidationError('starts_on', fn () => $this->reserve($user, $item, today()->subDay()->toDateString(), today()->toDateString()));
        $this->assertValidationError('ends_on', fn () => $this->reserve($user, $item, today()->addDays(3)->toDateString(), today()->addDay()->toDateString()));
    }

    public function test_bulk_items_cannot_be_reserved_yet(): void
    {
        $this->assertValidationError('item_id', fn () => $this->reserve(
            User::factory()->create(), Item::factory()->bulk()->create(), today()->toDateString(), today()->toDateString(),
        ));
    }

    public function test_a_manager_issues_and_returns_a_loan(): void
    {
        $manager = User::factory()->manager()->create();
        $loan = $this->reserve($manager, Item::factory()->create(), today()->toDateString(), today()->addDay()->toDateString());

        app(IssueLoan::class)->handle($manager, $loan);
        $this->assertSame(LoanStatus::Issued, $loan->status);
        $this->assertNotNull($loan->issued_at);

        app(ReturnLoan::class)->handle($manager, $loan);
        $this->assertSame(LoanStatus::Returned, $loan->refresh()->status);
        $this->assertNotNull($loan->returned_at);
    }

    public function test_an_early_handover_starts_the_period_today(): void
    {
        $manager = User::factory()->manager()->create();
        $loan = $this->reserve($manager, Item::factory()->create(), today()->addDays(3)->toDateString(), today()->addDays(5)->toDateString());

        app(IssueLoan::class)->handle($manager, $loan);

        $this->assertTrue($loan->refresh()->starts_on->isSameDay(today()));
    }

    public function test_an_item_that_has_not_come_back_cannot_be_issued_again(): void
    {
        $manager = User::factory()->manager()->create();
        $item = Item::factory()->create();
        Loan::factory()->overdue()->for($item)->create();
        $loan = $this->reserve($manager, $item, today()->toDateString(), today()->addDay()->toDateString());

        $this->assertValidationError('loan', fn () => app(IssueLoan::class)->handle($manager, $loan));
        $this->assertSame(LoanStatus::Reserved, $loan->refresh()->status);
    }

    public function test_borrowers_cannot_issue_their_own_reservation(): void
    {
        $borrower = User::factory()->borrower()->create();
        $reserved = $this->reserve($borrower, Item::factory()->create(), today()->toDateString(), today()->toDateString());

        $this->expectException(AuthorizationException::class);

        app(IssueLoan::class)->handle($borrower, $reserved);
    }

    public function test_borrowers_cannot_book_a_return(): void
    {
        $borrower = User::factory()->borrower()->create();
        $issued = Loan::factory()->issued()->for($borrower, 'borrower')->create();

        $this->expectException(AuthorizationException::class);

        app(ReturnLoan::class)->handle($borrower, $issued);
    }

    public function test_borrowers_can_cancel_their_own_reservations(): void
    {
        $borrower = User::factory()->borrower()->create();
        $own = $this->reserve($borrower, Item::factory()->create(), today()->toDateString(), today()->toDateString());

        app(CancelLoan::class)->handle($borrower, $own);

        $this->assertSame(LoanStatus::Cancelled, $own->refresh()->status);
    }

    public function test_borrowers_cannot_cancel_foreign_reservations(): void
    {
        $borrower = User::factory()->borrower()->create();

        $this->expectException(AuthorizationException::class);

        app(CancelLoan::class)->handle($borrower, Loan::factory()->create());
    }

    public function test_invalid_transitions_are_rejected_by_the_actions(): void
    {
        $manager = User::factory()->manager()->create();
        $reserved = Loan::factory()->create();
        $issued = Loan::factory()->issued()->create();

        $this->assertValidationError('loan', fn () => app(ReturnLoan::class)->handle($manager, $reserved));
        $this->assertValidationError('loan', fn () => app(CancelLoan::class)->handle($manager, $issued));
        $this->assertValidationError('loan', fn () => app(IssueLoan::class)->handle($manager, $issued));
    }
}
