<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hierarchische Standorte beliebiger Tiefe (R2), z. B. Gebäude → Raum → Regal → Fach.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('type', 40)->nullable();
            $table->timestamps();

            $table->index('parent_id');
        });

        DB::statement("ALTER TABLE locations ADD CONSTRAINT locations_name_not_blank CHECK (btrim(name) <> '')");
        DB::statement('ALTER TABLE locations ADD CONSTRAINT locations_not_own_parent CHECK (parent_id <> id)');
        DB::statement('ALTER TABLE locations ADD CONSTRAINT locations_name_unique_per_parent UNIQUE NULLS NOT DISTINCT (parent_id, name)');

        // Ein Standort darf nicht unter sich selbst oder einem seiner Unterstandorte hängen.
        // Die Advisory-Lock serialisiert Umhängungen, damit zwei gleichzeitige Moves keinen Zyklus bilden.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION cico_locations_prevent_cycle() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.parent_id IS NULL THEN
                    RETURN NEW;
                END IF;

                PERFORM pg_advisory_xact_lock(hashtext('cico.locations.tree'));

                IF EXISTS (
                    WITH RECURSIVE ancestors AS (
                        SELECT id, parent_id FROM locations WHERE id = NEW.parent_id
                        UNION ALL
                        SELECT l.id, l.parent_id FROM locations l JOIN ancestors a ON l.id = a.parent_id
                    ) CYCLE id SET is_cycle USING path
                    SELECT 1 FROM ancestors WHERE id = NEW.id
                ) THEN
                    RAISE EXCEPTION 'locations_no_cycle: Standort % kann nicht unter sich selbst oder einem eigenen Unterstandort liegen.', NEW.id
                        USING ERRCODE = 'check_violation', CONSTRAINT = 'locations_no_cycle';
                END IF;

                RETURN NEW;
            END;
            $$
            SQL);

        DB::statement('CREATE TRIGGER locations_prevent_cycle BEFORE INSERT OR UPDATE OF parent_id ON locations FOR EACH ROW EXECUTE FUNCTION cico_locations_prevent_cycle()');
        DB::statement("CREATE TRIGGER locations_audit AFTER INSERT OR UPDATE OR DELETE ON locations FOR EACH ROW EXECUTE FUNCTION cico_audit('{updated_at}', '{}')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('locations');
        DB::statement('DROP FUNCTION IF EXISTS cico_locations_prevent_cycle()');
    }
};
