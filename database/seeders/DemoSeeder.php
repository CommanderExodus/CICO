<?php

namespace Database\Seeders;

use App\Enums\ItemKind;
use App\Enums\LoanStatus;
use App\Enums\Role;
use App\Models\Location;
use App\Models\User;
use App\Support\PublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Realistische Demodaten (R38): mehrere Tausend Gegenstände, ein verschachtelter
 * Standortbaum, rund ein Jahr Ausleihhistorie und einige Fundmeldungen.
 *
 * Alles läuft durch dieselben Constraints und Audit-Trigger wie im Betrieb. Weil neue
 * Ausleihen nur als reserviert oder ausgegeben beginnen dürfen und je Gegenstand nur eine
 * gleichzeitig ausgegeben sein darf, wird die Historie in Runden angelegt: pro Runde je
 * Gegenstand höchstens eine Ausleihe, die anschließend zurückgebucht oder storniert wird.
 */
class DemoSeeder extends Seeder
{
    private const CHUNK = 500;

    private CarbonImmutable $today;

    public function run(): void
    {
        fake()->seed(20261026);
        mt_srand(20261026);

        $this->today = CarbonImmutable::today();

        $userIds = $this->seedUsers();
        $categoryIds = $this->seedCategories();
        $leavesByArea = $this->seedLocations();

        $this->seedItems((int) config('cico.demo.items'), $categoryIds, $leavesByArea);
        $this->seedLoans($userIds);
        $this->seedFoundReports($userIds);
    }

    /**
     * @return list<int> IDs der freigeschalteten Konten (nur diese leihen aus)
     */
    private function seedUsers(): array
    {
        // Die Demo-Konten sind in ihre Benachrichtigungsgruppen eingetragen, damit die Funktion sichtbar ist.
        User::factory()->admin()->create([
            'name' => 'Alex Admin', 'email' => 'admin@cico.test', 'notify_found_reports' => true, 'notify_registrations' => true,
        ]);
        User::factory()->manager()->create([
            'name' => 'Vera Verwalter', 'email' => 'verwalter@cico.test', 'notify_found_reports' => true,
        ]);
        User::factory()->borrower()->create(['name' => 'Anna Ausleiher', 'email' => 'ausleiher@cico.test']);

        User::factory()->manager()->count(2)->create();
        User::factory()->borrower()->count(60)->create();

        $active = User::query()->pluck('id')->all();

        // Ein paar frische Registrierungen, die noch auf Freischaltung warten.
        User::factory()->pending()->count(3)->create();

        return $active;
    }

    /**
     * @return array<string, int>
     */
    private function seedCategories(): array
    {
        $now = now();

        DB::table('categories')->insert(array_map(
            fn (string $name) => ['name' => $name, 'created_at' => $now, 'updated_at' => $now],
            array_keys(DemoCatalog::CATEGORIES),
        ));

        return DB::table('categories')->pluck('id', 'name')->all();
    }

    /**
     * Legt den Standortbaum an und liefert je Bereich (z. B. „Vereinsheim › Lager“) die IDs der Blätter.
     *
     * @return array<string, list<int>>
     */
    private function seedLocations(): array
    {
        $leaves = [];

        $create = function (array $nodes, ?int $parentId, array $path) use (&$create, &$leaves): void {
            foreach ($nodes as $name => [$type, $children]) {
                $location = Location::query()->create(['parent_id' => $parentId, 'name' => $name, 'type' => $type]);
                $locationPath = [...$path, $name];

                if ($children === []) {
                    // Ein Blatt gehört zu jedem Bereich entlang seines Pfads.
                    for ($depth = 1; $depth <= count($locationPath); $depth++) {
                        $leaves[implode(' › ', array_slice($locationPath, 0, $depth))][] = $location->id;
                    }

                    continue;
                }

                $create($children, $location->id, $locationPath);
            }
        };

        $create(DemoCatalog::locations(), null, []);

        return $leaves;
    }

    /**
     * @param  array<string, int>  $categoryIds
     * @param  array<string, list<int>>  $leavesByArea
     */
    private function seedItems(int $count, array $categoryIds, array $leavesByArea): void
    {
        $locationFor = function (string $category) use ($leavesByArea): ?int {
            if (mt_rand(1, 100) <= 3) {
                return null;
            }

            $areas = DemoCatalog::CATEGORIES[$category];

            return Arr::random($leavesByArea[Arr::random($areas)]);
        };

        $unitTemplates = collect(DemoCatalog::UNITS)
            ->flatMap(fn (array $templates, string $category) => array_map(fn ($t) => [$category, ...$t], $templates))
            ->values()->all();
        $bulkTemplates = collect(DemoCatalog::BULK)
            ->flatMap(fn (array $templates, string $category) => array_map(fn ($t) => [$category, ...$t], $templates))
            ->values()->all();

        $rows = [];
        $now = now();

        for ($i = 1; $i <= $count; $i++) {
            $isBulk = mt_rand(1, 100) <= 12;

            if ($isBulk) {
                [$category, $name, $min, $max] = Arr::random($bulkTemplates);
                $kind = ItemKind::Bulk;
                $serial = null;
                $quantity = mt_rand($min, $max);
            } else {
                [$category, $name, $prefix] = Arr::random($unitTemplates);
                $kind = ItemKind::Unit;
                $serial = sprintf('%s-%d-%05d', $prefix, mt_rand(2015, 2026), $i);
                $quantity = null;
            }

            $createdAt = $now->subDays(mt_rand(400, 1500));

            $rows[] = [
                'public_id' => PublicId::generate(),
                'kind' => $kind->value,
                'name' => $name,
                'description' => mt_rand(1, 100) <= 15 ? fake()->sentence(mt_rand(6, 14)) : null,
                'category_id' => $categoryIds[$category],
                'location_id' => $locationFor($category),
                'serial_number' => $serial,
                'quantity' => $quantity,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) === self::CHUNK) {
                DB::table('items')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('items')->insert($rows);
        }
    }

    /**
     * @param  list<int>  $userIds
     */
    private function seedLoans(array $userIds): void
    {
        $unitIds = DB::table('items')->where('kind', ItemKind::Unit->value)->pluck('id')->all();
        $rounds = [];
        $current = [];

        foreach ($unitIds as $itemId) {
            if (mt_rand(1, 100) > 45) {
                continue;
            }

            // Die Historie endet spätestens 20 Tage vor heute, damit aktuelle und überfällige Ausleihen danach Platz haben.
            $cursor = $this->today->subDays(mt_rand(300, 365));
            $round = 0;

            while (true) {
                $start = $cursor->addDays(mt_rand(3, 45));
                $end = $start->addDays(mt_rand(0, 9));

                if ($end->gte($this->today->subDays(20))) {
                    break;
                }

                $rounds[$round++][] = $this->loanRow($itemId, Arr::random($userIds), $start, $end, mt_rand(1, 100) <= 8);
                $cursor = $end;
            }

            $roll = mt_rand(1, 100);

            if ($roll <= 12) {
                $start = $this->today->subDays(mt_rand(0, 5));
                $current[] = $this->loanRow($itemId, Arr::random($userIds), $start, $this->today->addDays(mt_rand(0, 7)), issued: true);
            } elseif ($roll <= 16) {
                $end = $this->today->subDays(mt_rand(1, 14));
                $current[] = $this->loanRow($itemId, Arr::random($userIds), $end->subDays(mt_rand(1, 6)), $end, issued: true);
            } elseif ($roll <= 32) {
                $start = $this->today->addDays(mt_rand(1, 30));
                $current[] = $this->loanRow($itemId, Arr::random($userIds), $start, $start->addDays(mt_rand(0, 6)));
            }
        }

        foreach ($rounds as $rows) {
            foreach (array_chunk($rows, self::CHUNK) as $chunk) {
                DB::table('loans')->insert($chunk);
            }

            // Alles, was jetzt ausgegeben bzw. reserviert ist, stammt aus dieser Runde der Historie.
            DB::table('loans')->where('status', LoanStatus::Issued->value)->update([
                'status' => LoanStatus::Returned->value,
                'returned_at' => DB::raw("greatest(issued_at + interval '1 hour', ends_on + time '18:00' - random() * interval '1 day')"),
            ]);
            DB::table('loans')->where('status', LoanStatus::Reserved->value)->update([
                'status' => LoanStatus::Cancelled->value,
                'cancelled_at' => DB::raw("starts_on - random() * interval '3 days'"),
            ]);
        }

        foreach (array_chunk($current, self::CHUNK) as $chunk) {
            DB::table('loans')->insert($chunk);
        }
    }

    /**
     * Ausgegebene Ausleihen erhalten issued_at; Reservierungen, die in der Historie
     * später storniert werden, starten als reserviert.
     *
     * @return array<string, mixed>
     */
    private function loanRow(int $itemId, int $borrowerId, CarbonImmutable $start, CarbonImmutable $end, bool $cancelled = false, ?bool $issued = null): array
    {
        $issued ??= ! $cancelled && $start->lte($this->today);
        $createdAt = $start->subDays(mt_rand(1, 21))->setTime(mt_rand(8, 20), mt_rand(0, 59));

        return [
            'item_id' => $itemId,
            'borrower_id' => $borrowerId,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'status' => $issued ? LoanStatus::Issued->value : LoanStatus::Reserved->value,
            'issued_at' => $issued ? $start->setTime(mt_rand(8, 19), mt_rand(0, 59)) : null,
            'notes' => mt_rand(1, 100) <= 10 ? Arr::random(['Für das Sommerfest', 'Jugendfreizeit', 'Abteilungsversammlung', 'Turnier am Wochenende', 'Bitte gereinigt zurückgeben']) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * @param  list<int>  $userIds
     */
    private function seedFoundReports(array $userIds): void
    {
        $managerIds = User::query()->whereIn('role', [Role::Manager, Role::Admin])->pluck('id')->all();
        $itemIds = DB::table('items')->inRandomOrder()->limit(30)->pluck('id')->all();
        $retention = (int) config('cico.found_reports.contact_retention_days');
        $rows = [];

        foreach ($itemIds as $index => $itemId) {
            $createdAt = now()->subDays($index < 8 ? mt_rand(0, 6) : mt_rand(10, 200))->setTime(mt_rand(7, 21), mt_rand(0, 59));
            $open = $index < 8;
            $expired = $createdAt->lt(now()->subDays($retention));
            $hasContact = mt_rand(1, 100) <= 65;

            $rows[] = [
                'item_id' => $itemId,
                'found_location' => Arr::random(['Parkplatz Sporthalle', 'Umkleide Herren', 'Bushaltestelle Hofweg', 'Vereinsheim Eingang', 'Sportplatz Tribüne', 'Spielplatz Stadtpark']),
                'message' => Arr::random(['Lag einfach herum, habe es beim Hausmeister abgegeben.', 'Liegt jetzt bei mir, bitte melden.', 'Habe es am Zaun gefunden und dort gelassen.', 'Ist leicht beschädigt.']),
                'contact' => $hasContact && ! $expired ? fake()->safeEmail() : null,
                'contact_purged_at' => $hasContact && $expired ? $createdAt->addDays($retention) : null,
                'status' => $open ? 'open' : 'resolved',
                'resolved_at' => $open ? null : $createdAt->addDays(mt_rand(1, 5)),
                'resolved_by' => $open ? null : Arr::random($managerIds),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        DB::table('found_reports')->insert($rows);
    }
}
