<?php

namespace Tests\Feature\Database;

use App\Actions\Locations\SaveLocation;
use App\Models\Location;
use App\Models\User;
use App\Support\LocationTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * R2: Hierarchische Standorte beliebiger Tiefe, ohne Zyklen.
 */
class LocationTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_locations_can_be_nested_arbitrarily_deep(): void
    {
        $building = Location::factory()->create(['name' => 'Vereinsheim']);
        $room = Location::factory()->childOf($building)->create(['name' => 'Lager']);
        $shelf = Location::factory()->childOf($room)->create(['name' => 'Regal A']);
        $compartment = Location::factory()->childOf($shelf)->create(['name' => 'Fach 3']);
        $box = Location::factory()->childOf($compartment)->create(['name' => 'Kiste 1']);

        $tree = new LocationTree;

        $this->assertSame('Vereinsheim › Lager › Regal A › Fach 3 › Kiste 1', $tree->path($box->id));
        $this->assertEqualsCanonicalizing(
            [$room->id, $shelf->id, $compartment->id, $box->id],
            $tree->descendantIdsAndSelf($room->id),
        );
    }

    public function test_a_location_cannot_become_its_own_parent(): void
    {
        $location = Location::factory()->create();

        // Der Zyklus-Trigger greift vor dem CHECK locations_not_own_parent, der als zweite Absicherung bleibt.
        $this->assertConstraintViolation('locations_no_cycle', fn () => $location->update(['parent_id' => $location->id]));
    }

    public function test_a_location_cannot_be_moved_below_its_own_descendant(): void
    {
        $building = Location::factory()->create();
        $room = Location::factory()->childOf($building)->create();
        $shelf = Location::factory()->childOf($room)->create();

        $this->assertConstraintViolation('locations_no_cycle', fn () => $building->update(['parent_id' => $shelf->id]));
    }

    public function test_names_are_unique_per_parent_including_the_top_level(): void
    {
        $building = Location::factory()->create(['name' => 'Vereinsheim']);
        Location::factory()->childOf($building)->create(['name' => 'Lager']);

        $this->assertConstraintViolation('locations_name_unique_per_parent', fn () => Location::factory()->create(['name' => 'Vereinsheim']));
        $this->assertConstraintViolation('locations_name_unique_per_parent', fn () => Location::factory()->childOf($building)->create(['name' => 'Lager']));

        // Gleicher Name unter anderem Elternteil ist erlaubt.
        $other = Location::factory()->create(['name' => 'Sporthalle']);
        Location::factory()->childOf($other)->create(['name' => 'Lager']);
        $this->assertSame(2, Location::query()->where('name', 'Lager')->count());
    }

    public function test_the_action_translates_a_cycle_into_a_validation_error(): void
    {
        $manager = User::factory()->manager()->create();
        $building = Location::factory()->create();
        $room = Location::factory()->childOf($building)->create();

        try {
            app(SaveLocation::class)->handle($manager, ['name' => $building->name, 'parent_id' => $room->id], $building);
            $this->fail('Ein Zyklus hätte abgelehnt werden müssen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('parent_id', $e->errors());
        }

        $this->assertNull($building->fresh()->parent_id);
    }

    public function test_locations_with_children_cannot_be_deleted(): void
    {
        $building = Location::factory()->create();
        Location::factory()->childOf($building)->create();

        $this->assertConstraintViolation('locations_parent_id_foreign', fn () => $building->delete());
    }
}
