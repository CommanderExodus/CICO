<?php

namespace Tests\Feature\Inventory;

use App\Enums\ItemKind;
use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Weboberfläche für Bestand, Standorte und Reservierungen (R1, R2, R5) als dünne
 * Adapter auf die Actions.
 */
class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_manager_creates_a_unit_item(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        $location = Location::factory()->create();

        Livewire::test('pages::items.form')
            ->set('kind', 'unit')
            ->set('name', 'Beamer Epson')
            ->set('serial_number', 'EPS-1')
            ->set('location_id', (string) $location->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('items.show', Item::query()->sole()));

        $item = Item::query()->sole();
        $this->assertSame(ItemKind::Unit, $item->kind);
        $this->assertSame($location->id, $item->location_id);
        $this->assertNull($item->quantity);
    }

    public function test_bulk_items_need_a_quantity_and_unit_items_a_unique_serial(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        Item::factory()->create(['serial_number' => 'DOPPELT']);

        Livewire::test('pages::items.form')->set('kind', 'bulk')->set('name', 'Kabel')->call('save')
            ->assertHasErrors(['quantity' => 'required']);

        Livewire::test('pages::items.form')->set('kind', 'unit')->set('name', 'Zelt')->set('serial_number', 'DOPPELT')->call('save')
            ->assertHasErrors(['serial_number']);
    }

    public function test_the_kind_of_an_item_cannot_be_changed(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        $item = Item::factory()->create();

        Livewire::test('pages::items.form', ['item' => $item])
            ->set('kind', 'bulk')
            ->set('name', 'Umbenannt')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(ItemKind::Unit, $item->refresh()->kind);
        $this->assertSame('Umbenannt', $item->name);
    }

    public function test_search_finds_items_by_name_serial_and_public_id_and_filters_by_location_subtree(): void
    {
        $this->actingAs(User::factory()->borrower()->create());

        $building = Location::factory()->create(['name' => 'Vereinsheim']);
        $shelf = Location::factory()->childOf(Location::factory()->childOf($building)->create())->create();

        $beamer = Item::factory()->create(['name' => 'Beamer Epson', 'location_id' => $shelf->id]);
        $tent = Item::factory()->create(['name' => 'Zelt', 'serial_number' => 'ZLT-77']);

        Livewire::test('pages::items.index')->set('search', 'beamer')->assertSee('Beamer Epson')->assertDontSee('Zelt');
        Livewire::test('pages::items.index')->set('search', 'ZLT-77')->assertSee('Zelt')->assertDontSee('Beamer Epson');
        Livewire::test('pages::items.index')->set('search', strtoupper($beamer->public_id))->assertSee('Beamer Epson');
        Livewire::test('pages::items.index')->set('location', (string) $building->id)->assertSee('Beamer Epson')->assertDontSee('Zelt');
    }

    public function test_a_borrower_reserves_from_the_item_page_and_sees_conflicts(): void
    {
        $borrower = User::factory()->borrower()->create();
        $item = Item::factory()->create();
        Loan::factory()->for($item)->between(today()->addDays(5)->toDateString(), today()->addDays(7)->toDateString())->create();

        $this->actingAs($borrower);

        Livewire::test('pages::items.show', ['item' => $item])
            ->set('starts_on', today()->addDay()->toDateString())
            ->set('ends_on', today()->addDays(2)->toDateString())
            ->call('reserve')
            ->assertHasNoErrors();

        Livewire::test('pages::items.show', ['item' => $item])
            ->set('starts_on', today()->addDays(6)->toDateString())
            ->set('ends_on', today()->addDays(8)->toDateString())
            ->call('reserve')
            ->assertHasErrors(['starts_on']);

        $this->assertSame(1, $borrower->loans()->count());
    }

    public function test_a_manager_can_reserve_and_issue_immediately(): void
    {
        $manager = User::factory()->manager()->create();
        $borrower = User::factory()->borrower()->create();
        $item = Item::factory()->create();

        $this->actingAs($manager);

        Livewire::test('pages::items.show', ['item' => $item])
            ->set('borrower_id', (string) $borrower->id)
            ->set('issue_now', true)
            ->call('reserve')
            ->assertHasNoErrors();

        $loan = $borrower->loans()->sole();
        $this->assertSame('issued', $loan->status->value);
    }

    public function test_locations_are_managed_and_cycles_are_reported(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        Livewire::test('pages::locations.index')
            ->call('create')
            ->set('name', 'Vereinsheim')
            ->set('type', 'Gebäude')
            ->call('save')
            ->assertHasNoErrors();

        $building = Location::query()->where('name', 'Vereinsheim')->sole();
        $room = Location::factory()->childOf($building)->create();

        Livewire::test('pages::locations.index')
            ->call('edit', $building->id)
            ->set('parent_id', (string) $room->id)
            ->call('save')
            ->assertHasErrors(['parent_id']);

        Livewire::test('pages::locations.index')
            ->call('delete', $building->id)
            ->assertHasErrors(['location']);
    }

    public function test_categories_in_use_cannot_be_deleted(): void
    {
        $this->actingAs(User::factory()->manager()->create());
        $category = Category::factory()->create();
        Item::factory()->create(['category_id' => $category->id]);

        Livewire::test('pages::categories.index')
            ->call('delete', $category->id)
            ->assertHasErrors(['category']);

        $this->assertModelExists($category);
    }
}
