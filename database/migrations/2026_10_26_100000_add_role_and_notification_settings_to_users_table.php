<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen (R21) und die Benachrichtigungsgruppen, in die sich Verwalter und Admins eintragen.
 * Neue Konten starten als „pending“ und sehen nichts, bis ein Admin sie freischaltet.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('pending');
            $table->boolean('notify_found_reports')->default(false);
            $table->boolean('notify_registrations')->default(false);
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin', 'manager', 'borrower', 'pending'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'notify_found_reports', 'notify_registrations']);
        });
    }
};
