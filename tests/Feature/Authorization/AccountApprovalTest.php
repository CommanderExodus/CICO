<?php

namespace Tests\Feature\Authorization;

use App\Actions\Users\ChangeUserRole;
use App\Actions\Users\RejectRegistration;
use App\Enums\Role;
use App\Models\Loan;
use App\Models\User;
use App\Notifications\UserRegistered;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Mcp\InteractsWithMcp;
use Tests\TestCase;

/**
 * Neue Registrierungen warten auf Freischaltung durch einen Admin und haben bis dahin keine Rechte.
 */
class AccountApprovalTest extends TestCase
{
    use InteractsWithMcp;
    use RefreshDatabase;

    private function register(string $email = 'neu@example.com'): User
    {
        $this->post(route('register.store'), [
            'name' => 'Neu Mitglied', 'email' => $email, 'password' => 'password', 'password_confirmation' => 'password',
        ]);

        return User::query()->where('email', $email)->sole();
    }

    public function test_new_registrations_wait_for_approval(): void
    {
        $user = $this->register();

        $this->assertSame(Role::Pending, $user->role);

        $this->get(route('dashboard'))->assertRedirect(route('pending'));
        $this->get(route('pending'))->assertOk()->assertSee('wartet auf Freischaltung');
    }

    public function test_pending_accounts_see_no_inventory_and_get_no_tokens(): void
    {
        $this->actingAs(User::factory()->pending()->create());

        foreach (['items.index', 'loans.index', 'api-tokens.index', 'profile.edit', 'notifications.edit'] as $route) {
            $this->get(route($route))->assertRedirect(route('pending'));
        }
    }

    public function test_pending_accounts_have_no_rights_via_mcp_even_with_an_old_token(): void
    {
        $user = User::factory()->borrower()->create();
        $token = $this->tokenFor($user);

        // Ein Admin stuft das Konto zurück auf „wartet“ – das alte Token hilft dann nicht mehr.
        $user->forceFill(['role' => Role::Pending])->save();

        $this->assertSame([], $this->mcp('tools/list', token: $token)->json('result.tools'));
        $this->mcp('tools/call', ['name' => 'search-items', 'arguments' => (object) []], $token)->assertJsonPath('error.code', -32602);
    }

    public function test_approved_users_no_longer_see_the_waiting_page(): void
    {
        $this->actingAs(User::factory()->borrower()->create());

        $this->get(route('pending'))->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_admins_approve_by_assigning_a_role(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->pending()->create();

        app(ChangeUserRole::class)->handle($admin, $user, Role::Borrower);

        $this->actingAs($user->refresh())->get(route('items.index'))->assertOk();
    }

    public function test_admins_can_reject_pending_registrations(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->pending()->create();

        Livewire::test('pages::users.index')->call('reject', $user->id)->assertHasNoErrors();

        $this->assertModelMissing($user);
    }

    public function test_only_pending_accounts_can_be_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->borrower()->create();
        Loan::factory()->for($member, 'borrower')->create();

        $this->expectException(ValidationException::class);

        app(RejectRegistration::class)->handle($admin, $member);
    }

    public function test_managers_cannot_reject_registrations(): void
    {
        $this->expectException(AuthorizationException::class);

        app(RejectRegistration::class)->handle(User::factory()->manager()->create(), User::factory()->pending()->create());
    }

    public function test_only_subscribed_admins_are_notified_about_registrations(): void
    {
        Notification::fake();

        $subscribedAdmin = User::factory()->admin()->create(['notify_registrations' => true]);
        $otherAdmin = User::factory()->admin()->create();
        $managerWithStaleFlag = User::factory()->manager()->create(['notify_registrations' => true]);

        $this->register();

        Notification::assertSentTo($subscribedAdmin, UserRegistered::class);
        Notification::assertNotSentTo([$otherAdmin, $managerWithStaleFlag], UserRegistered::class);
    }

    public function test_nobody_is_notified_about_registrations_without_subscribers(): void
    {
        Notification::fake();
        User::factory()->admin()->create();

        $this->register();

        Notification::assertNothingSent();
    }

    public function test_the_users_menu_shows_waiting_registrations(): void
    {
        User::factory()->pending()->count(2)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('users.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Ablehnen');
    }
}
