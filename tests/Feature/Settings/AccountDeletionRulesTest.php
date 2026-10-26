<?php

namespace Tests\Feature\Settings;

use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Revisionssicherheit beim Löschen des eigenen Kontos.
 */
class AccountDeletionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_with_loan_history_are_kept_and_stay_logged_in(): void
    {
        $user = User::factory()->create();
        Loan::factory()->for($user, 'borrower')->create();

        $this->actingAs($user);

        Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertModelExists($user);
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_only_admin_cannot_delete_their_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);

        $this->assertModelExists($admin);
    }

    public function test_api_tokens_are_removed_with_the_account(): void
    {
        $user = User::factory()->create();
        $user->createToken('Claude Desktop');

        $this->actingAs($user);

        Livewire::test('pages::settings.delete-user-modal')
            ->set('password', 'password')
            ->call('deleteUser')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertModelMissing($user);
    }
}
