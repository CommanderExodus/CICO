<?php

namespace Tests\Feature\Database;

use App\Support\ConstraintViolation;
use App\Support\PublicId;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R6/R37: Gleichzeitige Buchungen desselben Gegenstands führen nie zu Doppelvergabe.
 *
 * Zwei echte, getrennte Verbindungen simulieren zwei parallele Requests. Die Testdaten
 * werden committet (sonst sähe die zweite Verbindung sie nicht) und danach wieder entfernt.
 */
class ConcurrentBookingTest extends TestCase
{
    use RefreshDatabase;

    private Connection $first;

    private Connection $second;

    private int $itemId;

    private int $borrowerId;

    protected function setUp(): void
    {
        parent::setUp();

        $config = config('database.connections.pgsql');
        $this->first = DB::connectUsing('concurrency_first', $config, force: true);
        $this->second = DB::connectUsing('concurrency_second', $config, force: true);

        // Die zweite Verbindung wartet höchstens kurz auf Sperren der ersten.
        $this->second->statement("SET lock_timeout = '500ms'");

        $this->borrowerId = $this->first->table('users')->insertGetId([
            'name' => 'Parallel', 'email' => 'parallel-'.uniqid().'@example.com', 'password' => 'x',
        ]);
        $this->itemId = $this->first->table('items')->insertGetId([
            'public_id' => PublicId::generate(), 'kind' => 'unit', 'name' => 'Beamer', 'serial_number' => 'SN-'.uniqid(),
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->first->transactionLevel() > 0) {
            $this->first->rollBack();
        }

        // Ausleihen sind per Trigger unlöschbar; TRUNCATE ist hier nur für die Testaufräumung gedacht.
        $this->first->statement('TRUNCATE loans, found_reports, items, users CASCADE');

        DB::purge('concurrency_first');
        DB::purge('concurrency_second');

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function loan(string $from, string $to): array
    {
        return ['item_id' => $this->itemId, 'borrower_id' => $this->borrowerId, 'starts_on' => $from, 'ends_on' => $to];
    }

    private function assertWaitsForLock(callable $statement): void
    {
        try {
            $statement();
            $this->fail('Die zweite Buchung hätte auf die offene erste warten müssen.');
        } catch (QueryException $e) {
            $this->assertSame('55P03', $e->getCode(), $e->getMessage());
        }
    }

    public function test_a_parallel_overlapping_reservation_waits_and_is_then_rejected(): void
    {
        $this->first->beginTransaction();
        $this->first->table('loans')->insert($this->loan('2026-11-01', '2026-11-05'));

        // Solange die erste Buchung offen ist, kann die zweite nicht durch – sie wartet.
        $this->assertWaitsForLock(fn () => $this->second->table('loans')->insert($this->loan('2026-11-03', '2026-11-07')));

        $this->first->commit();

        try {
            $this->second->table('loans')->insert($this->loan('2026-11-03', '2026-11-07'));
            $this->fail('Die überlappende zweite Buchung hätte abgelehnt werden müssen.');
        } catch (QueryException $e) {
            $this->assertTrue(ConstraintViolation::is($e, 'loans_no_overlap'), $e->getMessage());
        }

        $this->assertSame(1, $this->first->table('loans')->where('item_id', $this->itemId)->count());
    }

    public function test_the_parallel_reservation_succeeds_if_the_first_one_is_rolled_back(): void
    {
        $this->first->beginTransaction();
        $this->first->table('loans')->insert($this->loan('2026-11-01', '2026-11-05'));

        $this->assertWaitsForLock(fn () => $this->second->table('loans')->insert($this->loan('2026-11-03', '2026-11-07')));

        $this->first->rollBack();

        $this->second->table('loans')->insert($this->loan('2026-11-03', '2026-11-07'));

        $this->assertSame(
            ['2026-11-03'],
            $this->first->table('loans')->where('item_id', $this->itemId)->pluck('starts_on')->all(),
        );
    }

    public function test_the_same_item_cannot_be_issued_twice_in_parallel(): void
    {
        $firstLoan = $this->first->table('loans')->insertGetId($this->loan(today()->toDateString(), today()->addDay()->toDateString()));
        $secondLoan = $this->first->table('loans')->insertGetId($this->loan(today()->addDays(5)->toDateString(), today()->addDays(6)->toDateString()));

        $issue = fn (Connection $connection, int $loanId) => $connection->table('loans')->where('id', $loanId)
            ->update(['status' => 'issued', 'issued_at' => now()]);

        $this->first->beginTransaction();
        $issue($this->first, $firstLoan);

        $this->assertWaitsForLock(fn () => $issue($this->second, $secondLoan));

        $this->first->commit();

        try {
            $issue($this->second, $secondLoan);
            $this->fail('Ein zweites gleichzeitiges Ausgeben hätte abgelehnt werden müssen.');
        } catch (QueryException $e) {
            $this->assertTrue(ConstraintViolation::is($e, 'loans_one_issued_per_item'), $e->getMessage());
        }

        $this->assertSame(1, $this->first->table('loans')->where('item_id', $this->itemId)->where('status', 'issued')->count());
    }
}
