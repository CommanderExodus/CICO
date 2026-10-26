<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Item;
use App\Models\Loan;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R38: Seeder mit realistischen Demodaten. Im Test mit kleinerer Menge, damit er schnell bleibt;
 * die Standardmenge (3000 Gegenstände) läuft beim normalen db:seed.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_seeder_produces_consistent_data(): void
    {
        config(['cico.demo.items' => 400]);

        $this->seed(DemoSeeder::class);

        $this->assertSame(400, Item::query()->count());
        $this->assertGreaterThan(100, Location::query()->count());
        $this->assertTrue(User::query()->where('email', 'admin@cico.test')->where('role', Role::Admin)->exists());
        $this->assertTrue(User::query()->where('email', 'verwalter@cico.test')->where('role', Role::Manager)->exists());
        $this->assertTrue(User::query()->where('email', 'ausleiher@cico.test')->where('role', Role::Borrower)->exists());

        $this->assertGreaterThan(0, Loan::query()->where('status', 'returned')->count());
        $this->assertGreaterThan(0, Loan::query()->where('status', 'issued')->count());

        // Keine überlappenden aktiven Ausleihen – die Daten halten dieselben Regeln ein wie der Betrieb.
        $overlaps = DB::selectOne(<<<'SQL'
            select count(*) as aggregate
            from loans a join loans b on a.item_id = b.item_id and a.id < b.id
            where a.status in ('reserved', 'issued') and b.status in ('reserved', 'issued') and a.period && b.period
            SQL);
        $this->assertSame(0, (int) $overlaps->aggregate);
    }
}
