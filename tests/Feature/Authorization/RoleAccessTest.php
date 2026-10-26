<?php

namespace Tests\Feature\Authorization;

use App\Actions\Items\SaveItem;
use App\Actions\Users\ChangeUserRole;
use App\Enums\Role;
use App\Models\Item;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R21: Rollen Admin, Verwalter und Ausleiher mit jeweils eigenem Rechteumfang.
 */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: array<string, int>}>
     */
    public static function pages(): array
    {
        return [
            'Übersicht' => ['dashboard', ['admin' => 200, 'manager' => 200, 'borrower' => 200]],
            'Gegenstände' => ['items.index', ['admin' => 200, 'manager' => 200, 'borrower' => 200]],
            'Gegenstand anlegen' => ['items.create', ['admin' => 200, 'manager' => 200, 'borrower' => 403]],
            'Ausleihen' => ['loans.index', ['admin' => 200, 'manager' => 200, 'borrower' => 200]],
            'Standorte' => ['locations.index', ['admin' => 200, 'manager' => 200, 'borrower' => 403]],
            'Kategorien' => ['categories.index', ['admin' => 200, 'manager' => 200, 'borrower' => 403]],
            'Fundmeldungen' => ['found-reports.index', ['admin' => 200, 'manager' => 200, 'borrower' => 403]],
            'Benutzer' => ['users.index', ['admin' => 200, 'manager' => 403, 'borrower' => 403]],
            'Audit-Trail' => ['audit.index', ['admin' => 200, 'manager' => 403, 'borrower' => 403]],
            'MCP-Zugang' => ['api-tokens.index', ['admin' => 200, 'manager' => 200, 'borrower' => 200]],
        ];
    }

    /**
     * @param  array<string, int>  $expected
     */
    #[DataProvider('pages')]
    public function test_pages_respect_roles(string $route, array $expected): void
    {
        foreach ($expected as $role => $status) {
            $user = User::factory()->create(['role' => Role::from($role)]);

            $this->actingAs($user)->get(route($route))->assertStatus($status);
        }
    }

    public function test_item_pages_respect_roles(): void
    {
        $item = Item::factory()->create();

        $this->actingAs(User::factory()->borrower()->create());
        $this->get(route('items.show', $item))->assertOk();
        $this->get(route('items.edit', $item))->assertForbidden();
        $this->get(route('items.label', $item))->assertForbidden();

        $this->actingAs(User::factory()->manager()->create());
        $this->get(route('items.edit', $item))->assertOk();
        $this->get(route('items.label', $item))->assertOk()->assertSee('<svg', false);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('items.index'))->assertRedirect(route('login'));
    }

    public function test_borrowers_cannot_create_items_through_the_service_layer(): void
    {
        $this->expectException(AuthorizationException::class);

        app(SaveItem::class)->handle(User::factory()->borrower()->create(), ['kind' => 'unit', 'name' => 'X', 'serial_number' => 'Y']);
    }

    public function test_admins_change_roles_but_managers_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->borrower()->create();

        app(ChangeUserRole::class)->handle($admin, $user, Role::Manager);
        $this->assertSame(Role::Manager, $user->refresh()->role);

        $this->expectException(AuthorizationException::class);
        app(ChangeUserRole::class)->handle($user, $admin, Role::Borrower);
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();

        try {
            app(ChangeUserRole::class)->handle($admin, $admin, Role::Manager);
            $this->fail('Der letzte Admin hätte nicht herabgestuft werden dürfen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('role', $e->errors());
        }

        $this->assertSame(Role::Admin, $admin->refresh()->role);
    }

    public function test_the_role_cannot_be_mass_assigned(): void
    {
        $user = new User(['name' => 'X', 'email' => 'x@example.com', 'password' => 'x', 'role' => 'admin']);

        $this->assertSame(Role::Pending, $user->role);
    }
}
