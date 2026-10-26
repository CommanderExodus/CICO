<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gegenstände (R1): Einzelgegenstände (kind = unit) tragen eine Seriennummer,
 * Mengenware (kind = bulk) eine Menge. Welche Felder zu welcher Art gehören,
 * erzwingt die Datenbank per CHECK-Constraint.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 16)->unique();
            $table->string('kind', 10);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('serial_number', 100)->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamps();

            $table->index('category_id');
            $table->index('location_id');
        });

        DB::statement("ALTER TABLE items ADD CONSTRAINT items_kind_check CHECK (kind IN ('unit', 'bulk'))");
        DB::statement("ALTER TABLE items ADD CONSTRAINT items_name_not_blank CHECK (btrim(name) <> '')");

        // Nicht erratbare öffentliche Kennung (R11): 16 Zeichen Crockford-Base32 = 80 Bit Zufall.
        DB::statement("ALTER TABLE items ADD CONSTRAINT items_public_id_format CHECK (public_id ~ '^[0-9a-hjkmnp-tv-z]{16}$')");

        DB::statement(<<<'SQL'
            ALTER TABLE items ADD CONSTRAINT items_kind_fields CHECK (
                (kind = 'unit' AND serial_number IS NOT NULL AND btrim(serial_number) <> '' AND quantity IS NULL)
                OR (kind = 'bulk' AND serial_number IS NULL AND quantity IS NOT NULL AND quantity >= 0)
            )
            SQL);

        // Ziel des zusammengesetzten Fremdschlüssels loans(item_id, item_kind).
        DB::statement('ALTER TABLE items ADD CONSTRAINT items_id_kind_unique UNIQUE (id, kind)');

        DB::statement("CREATE UNIQUE INDEX items_serial_number_unique ON items (serial_number) WHERE kind = 'unit'");
        DB::statement('CREATE INDEX items_name_trgm_index ON items USING gin (name gin_trgm_ops)');

        DB::statement("CREATE TRIGGER items_audit AFTER INSERT OR UPDATE OR DELETE ON items FOR EACH ROW EXECUTE FUNCTION cico_audit('{updated_at}', '{}')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
