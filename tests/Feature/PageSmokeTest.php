<?php

namespace Tests\Feature;

use App\Models\FoundReport;
use App\Models\Item;
use App\Models\Loan;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rendert jede Seite mit Demodaten, damit Darstellungsfehler mit echten Inhalten auffallen.
 */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_renders_with_demo_data(): void
    {
        config(['cico.demo.items' => 150]);
        $this->seed(DemoSeeder::class);

        $admin = User::query()->where('email', 'admin@cico.test')->sole();
        $issuedLoan = Loan::query()->where('status', 'issued')->firstOrFail();
        $foundItem = FoundReport::query()->firstOrFail()->item;
        $bulk = Item::query()->where('kind', 'bulk')->firstOrFail();

        $this->actingAs($admin);

        foreach ([
            route('dashboard'),
            route('items.index'),
            route('items.index', ['search' => 'Beamer', 'availability' => 'on_loan']),
            route('items.show', $issuedLoan->item),
            route('items.show', $foundItem),
            route('items.show', $bulk),
            route('items.edit', $bulk),
            route('items.create'),
            route('items.label', $issuedLoan->item),
            route('loans.index'),
            route('loans.index', ['status' => 'all']),
            route('loans.index', ['status' => 'overdue']),
            route('locations.index'),
            route('categories.index'),
            route('found-reports.index', ['status' => 'all']),
            route('users.index'),
            route('audit.index'),
            route('audit.index', ['table' => 'loans']),
            route('api-tokens.index'),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs(User::query()->where('email', 'ausleiher@cico.test')->sole());
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('loans.index'))->assertOk();
        $this->get(route('items.show', $issuedLoan->item))->assertOk();
    }
}
