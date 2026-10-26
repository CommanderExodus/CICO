<?php

namespace Tests\Feature\Database;

use App\Enums\AuditChannel;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\FoundReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * R18: Vollständiger Audit-Trail aller Änderungen mit altem und neuem Wert.
 */
class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function entriesFor(string $table, int|string $id)
    {
        return AuditLog::query()->where('table_name', $table)->where('record_id', (string) $id)->orderBy('id')->get();
    }

    public function test_inserts_updates_and_deletes_are_logged_with_old_and_new_values(): void
    {
        $category = Category::query()->create(['name' => 'Zelte']);
        $category->update(['name' => 'Zelte & Pavillons']);
        $category->delete();

        [$created, $updated, $deleted] = $this->entriesFor('categories', $category->id)->all();

        $this->assertSame('created', $created->event);
        $this->assertNull($created->old_values);
        $this->assertSame('Zelte', $created->new_values['name']);

        $this->assertSame('updated', $updated->event);
        $this->assertSame(['name' => 'Zelte'], $updated->old_values);
        $this->assertSame(['name' => 'Zelte & Pavillons'], $updated->new_values);

        $this->assertSame('deleted', $deleted->event);
        $this->assertSame('Zelte & Pavillons', $deleted->old_values['name']);
        $this->assertNull($deleted->new_values);
    }

    public function test_changes_from_raw_sql_are_logged_too(): void
    {
        $category = Category::query()->create(['name' => 'Werkzeug']);

        DB::update('update categories set name = ? where id = ?', ['Werkzeuge', $category->id]);

        $this->assertSame(['name' => 'Werkzeuge'], $this->entriesFor('categories', $category->id)->last()->new_values);
    }

    public function test_updates_touching_only_ignored_columns_are_not_logged(): void
    {
        $category = Category::query()->create(['name' => 'Bälle']);

        $category->touch();

        $this->assertCount(1, $this->entriesFor('categories', $category->id));
    }

    public function test_secrets_and_finder_contacts_are_redacted(): void
    {
        $user = User::factory()->create();
        $report = FoundReport::factory()->create(['contact' => 'finder@example.com']);
        $report->update(['contact' => null]);

        $userEntry = $this->entriesFor('users', $user->id)->first();
        $this->assertSame('[geschwärzt]', $userEntry->new_values['password']);

        [$created, $purged] = $this->entriesFor('found_reports', $report->id)->all();
        $this->assertSame('[geschwärzt]', $created->new_values['contact']);
        $this->assertSame(['contact' => '[geschwärzt]'], $purged->old_values);
        $this->assertSame(['contact' => null], $purged->new_values);

        $this->assertSame(0, AuditLog::query()->whereRaw("old_values::text ilike '%finder@example.com%' or new_values::text ilike '%finder@example.com%'")->count());
    }

    public function test_the_audit_trail_is_append_only(): void
    {
        Category::query()->create(['name' => 'Küche']);
        $entry = AuditLog::query()->firstOrFail();

        $this->assertConstraintViolation('audit_logs_immutable', fn () => DB::table('audit_logs')->where('id', $entry->id)->update(['event' => 'deleted']));
        $this->assertConstraintViolation('audit_logs_immutable', fn () => DB::table('audit_logs')->where('id', $entry->id)->delete());
        $this->assertConstraintViolation('audit_logs_immutable', fn () => DB::statement('truncate audit_logs'));
    }

    public function test_web_requests_record_the_acting_user_and_channel(): void
    {
        Route::middleware(['web', 'auth'])->post('/_test/audit', fn () => Category::query()->create(['name' => 'Per Web']));

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post('/_test/audit')->assertCreated();

        $entry = AuditLog::query()->where('table_name', 'categories')->latest('id')->firstOrFail();
        $this->assertSame($manager->id, $entry->user_id);
        $this->assertSame(AuditChannel::Web, $entry->channel);
    }

    public function test_context_is_reapplied_after_a_rolled_back_transaction(): void
    {
        Route::middleware(['web', 'auth'])->post('/_test/audit-rollback', function () {
            try {
                DB::transaction(function () {
                    Category::query()->create(['name' => 'Wird zurückgerollt']);
                    throw new \RuntimeException('Abbruch');
                });
            } catch (\RuntimeException) {
                //
            }

            return Category::query()->create(['name' => 'Bleibt']);
        });

        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post('/_test/audit-rollback')->assertCreated();

        $entry = AuditLog::query()->where('table_name', 'categories')->latest('id')->firstOrFail();
        $this->assertSame('Bleibt', $entry->new_values['name']);
        $this->assertSame($manager->id, $entry->user_id);
    }
}
