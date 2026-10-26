<?php

namespace Tests\Feature\Database;

use App\Models\Item;
use App\Support\PublicId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R1, R11, R36: Welche Felder zu Einzelgegenstand bzw. Mengenware gehören und wie die
 * öffentliche Kennung aussieht, erzwingt die Datenbank selbst.
 */
class ItemConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_items_require_a_serial_number(): void
    {
        $this->assertConstraintViolation('items_kind_fields', fn () => DB::table('items')->insert([
            'public_id' => PublicId::generate(), 'kind' => 'unit', 'name' => 'Beamer',
        ]));
    }

    public function test_unit_items_cannot_have_a_quantity(): void
    {
        $this->assertConstraintViolation('items_kind_fields', fn () => DB::table('items')->insert([
            'public_id' => PublicId::generate(), 'kind' => 'unit', 'name' => 'Beamer', 'serial_number' => 'SN-1', 'quantity' => 3,
        ]));
    }

    public function test_bulk_items_require_a_non_negative_quantity_and_no_serial_number(): void
    {
        $this->assertConstraintViolation('items_kind_fields', fn () => DB::table('items')->insert([
            'public_id' => PublicId::generate(), 'kind' => 'bulk', 'name' => 'Kabel',
        ]));

        $this->assertConstraintViolation('items_kind_fields', fn () => DB::table('items')->insert([
            'public_id' => PublicId::generate(), 'kind' => 'bulk', 'name' => 'Kabel', 'quantity' => -1,
        ]));

        $this->assertConstraintViolation('items_kind_fields', fn () => DB::table('items')->insert([
            'public_id' => PublicId::generate(), 'kind' => 'bulk', 'name' => 'Kabel', 'quantity' => 5, 'serial_number' => 'SN-2',
        ]));
    }

    public function test_serial_numbers_of_unit_items_are_unique(): void
    {
        Item::factory()->create(['serial_number' => 'SN-42']);

        $this->assertConstraintViolation('items_serial_number_unique', fn () => Item::factory()->create(['serial_number' => 'SN-42']));
    }

    public function test_public_id_is_generated_and_not_sequential(): void
    {
        $first = Item::factory()->create();
        $second = Item::factory()->create();

        $this->assertTrue(PublicId::isValid($first->public_id));
        $this->assertTrue(PublicId::isValid($second->public_id));
        $this->assertNotSame($first->public_id, $second->public_id);
        $this->assertStringNotContainsString((string) $first->id, $first->public_id);
    }

    public function test_public_id_must_have_the_unguessable_format(): void
    {
        foreach (['42', 'ABCDEFGHJKMNPQRS', '0123456789abcdei', '0123456789abcdeu'] as $index => $publicId) {
            $this->assertConstraintViolation('items_public_id_format', fn () => DB::table('items')->insert([
                'public_id' => $publicId, 'kind' => 'unit', 'name' => 'Beamer', 'serial_number' => "SN-{$index}",
            ]));
        }
    }
}
