<?php

namespace Tests;

use App\Support\ConstraintViolation;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use Livewire\Livewire;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // @fluxScripts erzwingt die Livewire-Asset-Injektion über ein statisches Flag, das im
        // Betrieb pro Request-Prozess frisch ist, im Test-Prozess aber in den nächsten Test wandert.
        Livewire::flushState();
    }

    protected function skipUnlessFortifyFeature(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Erwartet, dass die Datenbank die Änderung mit dem benannten Constraint ablehnt.
     * Läuft in einem Savepoint, damit die Test-Transaktion danach weiter nutzbar ist.
     */
    protected function assertConstraintViolation(string $constraint, Closure $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (QueryException $e) {
            $this->assertTrue(
                ConstraintViolation::is($e, $constraint),
                "Erwartet wurde eine Verletzung von [{$constraint}], tatsächlich: {$e->getMessage()}",
            );

            return;
        }

        $this->fail("Die Datenbank hätte die Änderung wegen [{$constraint}] ablehnen müssen.");
    }
}
