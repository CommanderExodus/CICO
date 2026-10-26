<?php

namespace Tests\Feature\Loans;

use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use App\Queries\CheckAvailability;
use App\Queries\ListLoans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R22: Ausleiher sehen nur eigene Ausleihen – in Listen, auf Detailseiten und bei Aktionen.
 */
class LoanVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $anna;

    private User $bernd;

    private Loan $annasLoan;

    private Loan $berndsLoan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->anna = User::factory()->borrower()->create(['name' => 'Anna Ausleiher']);
        $this->bernd = User::factory()->borrower()->create(['name' => 'Bernd Borger']);

        $this->annasLoan = Loan::factory()->for($this->anna, 'borrower')->for(Item::factory()->create(['name' => 'Annas Beamer']))->create();
        $this->berndsLoan = Loan::factory()->for($this->bernd, 'borrower')->for(Item::factory()->create(['name' => 'Bernds Zelt']))->create();
    }

    public function test_the_query_only_returns_own_loans_for_borrowers(): void
    {
        $ids = app(ListLoans::class)->query($this->anna, ['status' => 'all'])->pluck('id')->all();

        $this->assertSame([$this->annasLoan->id], $ids);
    }

    public function test_managers_see_all_loans(): void
    {
        $ids = app(ListLoans::class)->query(User::factory()->manager()->create(), ['status' => 'all'])->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$this->annasLoan->id, $this->berndsLoan->id], $ids);
    }

    public function test_the_loans_page_shows_only_own_loans(): void
    {
        $this->actingAs($this->anna)
            ->get(route('loans.index'))
            ->assertOk()
            ->assertSee('Annas Beamer')
            ->assertDontSee('Bernds Zelt')
            ->assertDontSee('Bernd Borger');
    }

    public function test_the_item_page_does_not_reveal_other_borrowers(): void
    {
        $this->actingAs($this->anna)
            ->get(route('items.show', $this->berndsLoan->item))
            ->assertOk()
            ->assertDontSee('Bernd Borger');
    }

    public function test_availability_hides_who_booked_for_borrowers_but_not_for_managers(): void
    {
        $item = $this->berndsLoan->item;
        $from = $this->berndsLoan->starts_on;
        $to = $this->berndsLoan->ends_on;

        $forAnna = app(CheckAvailability::class)->handle($this->anna, $item, $from, $to);
        $this->assertFalse($forAnna['available']);
        $this->assertNull($forAnna['conflicts'][0]['borrower']);

        $forManager = app(CheckAvailability::class)->handle(User::factory()->manager()->create(), $item, $from, $to);
        $this->assertSame('Bernd Borger', $forManager['conflicts'][0]['borrower']);
    }

    public function test_borrowers_cannot_act_on_foreign_loans_by_guessing_ids(): void
    {
        $this->actingAs($this->anna);

        Livewire::test('pages::loans.index')->call('cancel', $this->berndsLoan->id)->assertNotFound();

        $this->assertSame('reserved', $this->berndsLoan->refresh()->status->value);
    }
}
