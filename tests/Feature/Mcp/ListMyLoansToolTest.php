<?php

namespace Tests\Feature\Mcp;

use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R28/R22: Lesendes Tool „eigene Ausleihen anzeigen“ – nie die Ausleihen anderer.
 */
class ListMyLoansToolTest extends TestCase
{
    use InteractsWithMcp;
    use RefreshDatabase;

    public function test_it_lists_only_the_callers_own_loans(): void
    {
        $anna = User::factory()->borrower()->create();
        $own = Loan::factory()->for($anna, 'borrower')->for(Item::factory()->create(['name' => 'Annas Beamer']))->create();
        Loan::factory()->for(Item::factory()->create(['name' => 'Fremdes Zelt']))->create();

        $this->callTool($anna, 'list-my-loans')
            ->assertJsonPath('result.structuredContent.count', 1)
            ->assertJsonPath('result.structuredContent.loans.0.id', $own->id)
            ->assertJsonPath('result.structuredContent.loans.0.item.name', 'Annas Beamer')
            ->assertDontSee('Fremdes Zelt');
    }

    public function test_managers_also_get_only_their_own_loans_from_this_tool(): void
    {
        $manager = User::factory()->manager()->create();
        Loan::factory()->create();

        $this->callTool($manager, 'list-my-loans')->assertJsonPath('result.structuredContent.count', 0);
    }

    public function test_overdue_is_derived_and_status_can_be_filtered(): void
    {
        $anna = User::factory()->borrower()->create();
        $overdue = Loan::factory()->overdue()->for($anna, 'borrower')->create();
        $reserved = Loan::factory()->for($anna, 'borrower')->create();

        $active = $this->callTool($anna, 'list-my-loans')->json('result.structuredContent.loans');
        $this->assertEqualsCanonicalizing([$overdue->id, $reserved->id], array_column($active, 'id'));
        $this->assertSame([true], array_values(array_column(array_filter($active, fn ($loan) => $loan['id'] === $overdue->id), 'overdue')));

        $this->callTool($anna, 'list-my-loans', ['status' => 'overdue'])
            ->assertJsonPath('result.structuredContent.count', 1)
            ->assertJsonPath('result.structuredContent.loans.0.status', 'issued');

        $this->callTool($anna, 'list-my-loans', ['status' => 'kaputt'])->assertJsonPath('result.isError', true);
    }
}
