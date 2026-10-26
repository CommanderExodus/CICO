<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit-Trail (R18): Jede Änderung an fachlichen Tabellen wird per Trigger
 * mit altem und neuem Wert protokolliert – unabhängig davon, ob sie aus der
 * Weboberfläche, dem MCP-Server, der Konsole oder direktem SQL stammt.
 *
 * Wer die Änderung ausgelöst hat, liest der Trigger aus den Session-Variablen
 * cico.user_id, cico.channel und cico.client (gesetzt von App\Support\AuditContext).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE audit_logs (
                id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                occurred_at timestamptz NOT NULL DEFAULT now(),
                event       varchar(30) NOT NULL,
                table_name  varchar(63),
                record_id   varchar(64),
                old_values  jsonb,
                new_values  jsonb,
                user_id     bigint,
                channel     varchar(20) NOT NULL,
                client      varchar(255),
                meta        jsonb,
                CONSTRAINT audit_logs_event_check CHECK (event IN ('created', 'updated', 'deleted', 'mcp.request')),
                CONSTRAINT audit_logs_channel_check CHECK (channel IN ('web', 'public', 'mcp', 'console', 'system'))
            )
            SQL);

        DB::statement('CREATE INDEX audit_logs_record_index ON audit_logs (table_name, record_id)');
        DB::statement('CREATE INDEX audit_logs_occurred_at_index ON audit_logs (occurred_at DESC)');
        DB::statement('CREATE INDEX audit_logs_user_id_index ON audit_logs (user_id)');

        // Geschwärzte Spalten (Passwörter, Tokens, Kontaktdaten) tauchen nur als Platzhalter auf.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION cico_audit_value(value jsonb, redact boolean) RETURNS jsonb
            LANGUAGE sql IMMUTABLE AS $$
                SELECT CASE WHEN redact AND value <> 'null'::jsonb THEN '"[geschwärzt]"'::jsonb ELSE value END
            $$
            SQL);

        // TG_ARGV[0]: ignorierte Spalten, TG_ARGV[1]: geschwärzte Spalten (jeweils als Array-Literal).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION cico_audit() RETURNS trigger
            LANGUAGE plpgsql AS $$
            DECLARE
                ignored    text[] := TG_ARGV[0]::text[];
                redacted   text[] := TG_ARGV[1]::text[];
                old_row    jsonb;
                new_row    jsonb;
                old_values jsonb := '{}'::jsonb;
                new_values jsonb := '{}'::jsonb;
                col        text;
            BEGIN
                IF TG_OP <> 'INSERT' THEN old_row := to_jsonb(OLD); END IF;
                IF TG_OP <> 'DELETE' THEN new_row := to_jsonb(NEW); END IF;

                FOR col IN SELECT jsonb_object_keys(coalesce(new_row, old_row)) LOOP
                    CONTINUE WHEN col = ANY (ignored);
                    CONTINUE WHEN TG_OP = 'UPDATE' AND old_row -> col IS NOT DISTINCT FROM new_row -> col;

                    IF old_row IS NOT NULL THEN
                        old_values := old_values || jsonb_build_object(col, cico_audit_value(old_row -> col, col = ANY (redacted)));
                    END IF;

                    IF new_row IS NOT NULL THEN
                        new_values := new_values || jsonb_build_object(col, cico_audit_value(new_row -> col, col = ANY (redacted)));
                    END IF;
                END LOOP;

                -- Updates, die nur ignorierte Spalten (z. B. updated_at) betreffen, sind keine fachliche Änderung.
                IF TG_OP = 'UPDATE' AND new_values = '{}'::jsonb THEN
                    RETURN NULL;
                END IF;

                INSERT INTO audit_logs (event, table_name, record_id, old_values, new_values, user_id, channel, client)
                VALUES (
                    CASE TG_OP WHEN 'INSERT' THEN 'created' WHEN 'UPDATE' THEN 'updated' ELSE 'deleted' END,
                    TG_TABLE_NAME,
                    coalesce(new_row ->> 'id', old_row ->> 'id'),
                    CASE WHEN TG_OP = 'INSERT' THEN NULL ELSE old_values END,
                    CASE WHEN TG_OP = 'DELETE' THEN NULL ELSE new_values END,
                    nullif(current_setting('cico.user_id', true), '')::bigint,
                    coalesce(nullif(current_setting('cico.channel', true), ''), 'system'),
                    nullif(current_setting('cico.client', true), '')
                );

                RETURN NULL;
            END;
            $$
            SQL);

        // Revisionssicherheit: Der Audit-Trail kann nur ergänzt, nie geändert oder geleert werden.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION cico_audit_logs_immutable() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs_immutable: Der Audit-Trail ist unveränderlich (% ist nicht erlaubt).', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$
            SQL);

        DB::statement('CREATE TRIGGER audit_logs_immutable BEFORE UPDATE OR DELETE ON audit_logs FOR EACH ROW EXECUTE FUNCTION cico_audit_logs_immutable()');
        DB::statement('CREATE TRIGGER audit_logs_no_truncate BEFORE TRUNCATE ON audit_logs FOR EACH STATEMENT EXECUTE FUNCTION cico_audit_logs_immutable()');

        DB::statement(<<<'SQL'
            CREATE TRIGGER users_audit AFTER INSERT OR UPDATE OR DELETE ON users
            FOR EACH ROW EXECUTE FUNCTION cico_audit(
                '{updated_at,remember_token}',
                '{password,two_factor_secret,two_factor_recovery_codes}'
            )
            SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER personal_access_tokens_audit AFTER INSERT OR UPDATE OR DELETE ON personal_access_tokens
            FOR EACH ROW EXECUTE FUNCTION cico_audit('{updated_at,last_used_at}', '{token}')
            SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS personal_access_tokens_audit ON personal_access_tokens');
        DB::statement('DROP TRIGGER IF EXISTS users_audit ON users');
        DB::statement('DROP TABLE IF EXISTS audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS cico_audit_logs_immutable()');
        DB::statement('DROP FUNCTION IF EXISTS cico_audit()');
        DB::statement('DROP FUNCTION IF EXISTS cico_audit_value(jsonb, boolean)');
    }
};
