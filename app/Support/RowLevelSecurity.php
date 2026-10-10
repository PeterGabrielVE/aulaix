<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The PostgreSQL side of tenant isolation (F1-02), in one place: turning
 * Row Level Security on for a tenant-scoped table, and pointing the current
 * DB session at an institution so those policies let its rows through.
 *
 * A new tenant-scoped table needs two lines in its migration:
 *
 *     Schema::create('sections', function (Blueprint $table) {
 *         $table->id();
 *         $table->belongsToInstitution();   // see AppServiceProvider
 *         ...
 *     });
 *
 *     RowLevelSecurity::enable('sections');
 *
 * tests/Feature/TenantTableTest.php fails for any table that has an
 * institution_id column but no policy, so the second line can't be
 * forgotten silently.
 */
class RowLevelSecurity
{
    /**
     * The Postgres session variable every tenant_isolation policy compares
     * institution_id against.
     */
    public const SESSION_VARIABLE = 'app.current_institution_id';

    /**
     * SQL for the current institution's id, or NULL when the session has no
     * tenant. The variable reads as NULL if never set but as '' once cleared,
     * hence nullif(): ''::bigint would make every query fail instead of
     * returning nothing.
     */
    public const CURRENT_INSTITUTION_SQL = "nullif(current_setting('".self::SESSION_VARIABLE."', true), '')::bigint";

    /**
     * FORCE matters: the app's DB role owns the tables (it runs the
     * migrations), and Postgres exempts owners from RLS unless forced.
     * Without a tenant, institution_id = NULL matches no row, so the table
     * reads as empty and rejects every insert.
     */
    public static function enable(string $table): void
    {
        $current = self::CURRENT_INSTITUTION_SQL;

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY tenant_isolation ON {$table}
            USING (institution_id = {$current})
            WITH CHECK (institution_id = {$current})
        ");
    }

    public static function disable(string $table): void
    {
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }

    /**
     * Scope the rest of this DB session to the given institution. Session-
     * wide (not transaction-local) so it survives the transactions Eloquent
     * and the test suite open and close along the way.
     */
    public static function setInstitution(int $institutionId): void
    {
        DB::statement('select set_config(?, ?, false)', [self::SESSION_VARIABLE, (string) $institutionId]);
    }

    /**
     * Leave the current institution: from here on this DB session sees no
     * tenant-scoped rows at all. For long-lived processes (queue workers,
     * commands looping over institutions) that reuse one connection.
     */
    public static function clearInstitution(): void
    {
        DB::statement('select set_config(?, ?, false)', [self::SESSION_VARIABLE, '']);
    }
}
