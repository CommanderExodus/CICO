<?php

namespace Tests\Feature\Mcp;

use App\Models\Category;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R28: Lesendes Tool „Gegenstand suchen“.
 */
class SearchItemsToolTest extends TestCase
{
    use InteractsWithMcp;
    use RefreshDatabase;

    public function test_it_finds_items_with_location_path_and_availability(): void
    {
        $building = Location::factory()->create(['name' => 'Vereinsheim']);
        $room = Location::factory()->childOf($building)->create(['name' => 'Technikraum']);
        $category = Category::factory()->create(['name' => 'Veranstaltungstechnik']);

        $beamer = Item::factory()->create(['name' => 'Beamer Epson', 'location_id' => $room->id, 'category_id' => $category->id]);
        Loan::factory()->issued()->for($beamer)->create();
        Item::factory()->create(['name' => 'Zelt']);

        $result = $this->callTool(User::factory()->borrower()->create(), 'search-items', ['query' => 'beamer'])
            ->assertOk()
            ->assertJsonPath('result.isError', false)
            ->json('result.structuredContent');

        $this->assertSame(1, $result['total']);
        $this->assertSame([
            'id' => $beamer->id,
            'public_id' => $beamer->public_id,
            'name' => 'Beamer Epson',
            'kind' => 'unit',
            'category' => 'Veranstaltungstechnik',
            'location' => 'Vereinsheim › Technikraum',
            'serial_number' => $beamer->serial_number,
            'quantity' => null,
            'availability' => 'on_loan',
        ], $result['items'][0]);
    }

    public function test_it_filters_by_category_name_kind_and_location_subtree(): void
    {
        $building = Location::factory()->create();
        $shelf = Location::factory()->childOf(Location::factory()->childOf($building)->create())->create();
        $category = Category::factory()->create(['name' => 'Kabel']);

        $cable = Item::factory()->bulk(20)->create(['name' => 'Verlängerungskabel', 'category_id' => $category->id, 'location_id' => $shelf->id]);
        Item::factory()->create(['name' => 'Kabeltrommel', 'category_id' => $category->id]);

        $user = User::factory()->create();

        $this->assertSame([$cable->id], array_column(
            $this->callTool($user, 'search-items', ['category' => 'kabel', 'kind' => 'bulk'])->json('result.structuredContent.items'), 'id',
        ));

        $this->assertSame([$cable->id], array_column(
            $this->callTool($user, 'search-items', ['location_id' => $building->id])->json('result.structuredContent.items'), 'id',
        ));

        $this->assertSame(0, $this->callTool($user, 'search-items', ['category' => 'Gibt es nicht'])->json('result.structuredContent.total'));
    }

    public function test_invalid_arguments_return_a_tool_error(): void
    {
        $this->callTool(User::factory()->create(), 'search-items', ['kind' => 'rakete'])
            ->assertOk()
            ->assertJsonPath('result.isError', true);
    }
}
