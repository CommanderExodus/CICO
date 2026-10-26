<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fundmeldungen von der öffentlichen Statusseite (R14). Die optionalen Kontaktdaten
 * der Finder werden nach einer festen Frist gelöscht (R23) und im Audit-Trail geschwärzt.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('found_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->string('found_location', 255);
            $table->text('message');
            $table->string('contact', 255)->nullable();
            $table->string('status', 10)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('contact_purged_at')->nullable();
            $table->timestamps();

            $table->index('item_id');
            $table->index(['status', 'created_at']);
        });

        DB::statement("ALTER TABLE found_reports ADD CONSTRAINT found_reports_status_check CHECK (status IN ('open', 'resolved'))");
        DB::statement("ALTER TABLE found_reports ADD CONSTRAINT found_reports_location_not_blank CHECK (btrim(found_location) <> '')");
        DB::statement("ALTER TABLE found_reports ADD CONSTRAINT found_reports_message_not_blank CHECK (btrim(message) <> '')");
        DB::statement("ALTER TABLE found_reports ADD CONSTRAINT found_reports_resolved_matches_status CHECK ((status = 'resolved') = (resolved_at IS NOT NULL))");
        DB::statement('ALTER TABLE found_reports ADD CONSTRAINT found_reports_purged_contact_empty CHECK (contact_purged_at IS NULL OR contact IS NULL)');

        DB::statement("CREATE TRIGGER found_reports_audit AFTER INSERT OR UPDATE OR DELETE ON found_reports FOR EACH ROW EXECUTE FUNCTION cico_audit('{updated_at}', '{contact}')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('found_reports');
    }
};
