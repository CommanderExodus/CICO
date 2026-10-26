<?php

namespace Tests\Feature\FoundReports;

use App\Models\AuditLog;
use App\Models\FoundReport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R23: Kontaktdaten der Finder werden nach einer festen Frist automatisch gelöscht.
 */
class PurgeContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contacts_older_than_the_retention_period_are_deleted(): void
    {
        config(['cico.found_reports.contact_retention_days' => 30]);

        $old = FoundReport::factory()->create(['contact' => 'alt@example.com', 'created_at' => now()->subDays(31)]);
        $fresh = FoundReport::factory()->create(['contact' => 'neu@example.com', 'created_at' => now()->subDays(29)]);

        $this->artisan('found-reports:purge-contacts')->assertSuccessful();

        $old->refresh();
        $this->assertNull($old->contact);
        $this->assertNotNull($old->contact_purged_at);
        $this->assertSame($old->getOriginal('message'), $old->message);

        $this->assertSame('neu@example.com', $fresh->refresh()->contact);
    }

    public function test_purged_contacts_do_not_survive_in_the_audit_trail(): void
    {
        FoundReport::factory()->create(['contact' => 'alt@example.com', 'created_at' => now()->subDays(100)]);

        $this->artisan('found-reports:purge-contacts')->assertSuccessful();

        $leaks = AuditLog::query()
            ->whereRaw("coalesce(old_values::text, '') ilike '%alt@example.com%' or coalesce(new_values::text, '') ilike '%alt@example.com%'")
            ->count();

        $this->assertSame(0, $leaks);
    }

    public function test_a_purged_report_cannot_receive_a_contact_again(): void
    {
        $report = FoundReport::factory()->create(['contact' => null]);
        $report->forceFill(['contact_purged_at' => now()])->save();

        $this->assertConstraintViolation('found_reports_purged_contact_empty', fn () => $report->update(['contact' => 'wieder@example.com']));
    }

    public function test_the_purge_runs_daily(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'found-reports:purge-contacts'));

        $this->assertCount(1, $events);
        $this->assertSame('0 3 * * *', $events->first()->expression);
    }
}
