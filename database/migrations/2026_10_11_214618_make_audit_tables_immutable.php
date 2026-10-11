<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-005 AUD-R03: audit entries are append-only. These triggers reject any
 * UPDATE, DELETE or TRUNCATE, including raw SQL run with the application's
 * own role. (Dropping the trigger needs DDL, which only migrations do; see
 * the residual risk noted in specs/005-auditoria/design.md.)
 */
return new class extends Migration
{
    private const TABLES = ['audit_entries', 'platform_audit_entries'];

    public function up(): void
    {
        // OR REPLACE: migrate:fresh drops tables but not functions, so the
        // function can outlive the tables it guarded.
        DB::statement("
            CREATE OR REPLACE FUNCTION reject_audit_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit entries are immutable (% on %)', TG_OP, TG_TABLE_NAME;
            END;
            $$
        ");

        foreach (self::TABLES as $table) {
            DB::statement("
                CREATE TRIGGER {$table}_immutable_rows
                BEFORE UPDATE OR DELETE ON {$table}
                FOR EACH ROW EXECUTE FUNCTION reject_audit_change()
            ");
            DB::statement("
                CREATE TRIGGER {$table}_immutable_table
                BEFORE TRUNCATE ON {$table}
                FOR EACH STATEMENT EXECUTE FUNCTION reject_audit_change()
            ");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_rows ON {$table}");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_table ON {$table}");
        }

        DB::statement('DROP FUNCTION IF EXISTS reject_audit_change()');
    }
};
