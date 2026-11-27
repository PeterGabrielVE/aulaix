<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Persistence;

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
final class RowLevelSecurity
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

    /**
     * Policy names of enableWithCentralReads(), in creation order.
     */
    public const CENTRAL_READ_POLICIES = ['central_read', 'tenant_insert', 'tenant_update', 'tenant_delete'];

    /**
     * Like enable(), except that with no institution active every row is
     * readable (writes still need the row's institution to be active). Only
     * for the tables the central domain reads to authenticate a person
     * across their institutions (ADR 0006 C).
     */
    public static function enableWithCentralReads(string $table): void
    {
        $current = self::CURRENT_INSTITUTION_SQL;
        $own = "(institution_id = {$current})";

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        DB::statement("CREATE POLICY central_read ON {$table} FOR SELECT USING ({$current} IS NULL OR institution_id = {$current})");
        DB::statement("CREATE POLICY tenant_insert ON {$table} FOR INSERT WITH CHECK {$own}");
        DB::statement("CREATE POLICY tenant_update ON {$table} FOR UPDATE USING {$own} WITH CHECK {$own}");
        DB::statement("CREATE POLICY tenant_delete ON {$table} FOR DELETE USING {$own}");
    }

    /**
     * Back from enableWithCentralReads() to enable().
     */
    public static function disableCentralReads(string $table): void
    {
        foreach (self::CENTRAL_READ_POLICIES as $policy) {
            DB::statement("DROP POLICY IF EXISTS {$policy} ON {$table}");
        }

        $current = self::CURRENT_INSTITUTION_SQL;

        DB::statement("CREATE POLICY tenant_isolation ON {$table} USING (institution_id = {$current}) WITH CHECK (institution_id = {$current})");
    }

    /**
     * Policy names of enableAddressedByIdentification(), in creation order.
     */
    public const ADDRESSED_POLICIES = ['addressed_read', 'addressed_insert', 'addressed_update'];

    /**
     * For requests one institution addresses to whichever institution holds
     * an identification (ADR 0008, the transfers of SPEC-009). A row is
     * visible and answerable to the institution that created it, to the one
     * that answered it (origin_institution_id), and, while pending, to the
     * institution that has an active row of $holders with that
     * identification. $holders is under its own Row Level Security, so the
     * subquery only ever looks at the active institution's rows. Rows are
     * never deleted.
     */
    public static function enableAddressedByIdentification(string $table, string $holders): void
    {
        $current = self::CURRENT_INSTITUTION_SQL;
        $visible = "(institution_id = {$current} OR origin_institution_id = {$current}"
            ." OR (status = 'pending' AND EXISTS (SELECT 1 FROM {$holders} holder WHERE holder.identification = {$table}.identification AND holder.status = 'active')))";

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("CREATE POLICY addressed_read ON {$table} FOR SELECT USING {$visible}");
        DB::statement("CREATE POLICY addressed_insert ON {$table} FOR INSERT WITH CHECK (institution_id = {$current})");
        DB::statement("CREATE POLICY addressed_update ON {$table} FOR UPDATE USING {$visible} WITH CHECK (institution_id = {$current} OR origin_institution_id = {$current})");
    }

    /**
     * SPEC-015 RLT-R05: who answered a request addressed by identification
     * can not be faked. A request is created with no origin and pending; the
     * origin is set once, by the institution that answers it (not the one
     * that asked), and never changed after; what was asked never changes.
     * A plain trigger, run as the application's role: no privilege added
     * (S-03). A new table of this kind calls it right after
     * enableAddressedByIdentification(). The function is created OR
     * REPLACEd: migrate:fresh drops tables, not functions.
     */
    public static function guardAddressedOrigin(string $table): void
    {
        $current = self::CURRENT_INSTITUTION_SQL;

        DB::statement("DROP POLICY addressed_insert ON {$table}");
        DB::statement("CREATE POLICY addressed_insert ON {$table} FOR INSERT WITH CHECK (institution_id = {$current} AND origin_institution_id IS NULL AND status = 'pending')");

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION guard_{$table}_origin() RETURNS trigger LANGUAGE plpgsql AS \$\$
            BEGIN
                IF NEW.institution_id IS DISTINCT FROM OLD.institution_id OR NEW.identification IS DISTINCT FROM OLD.identification THEN
                    RAISE EXCEPTION '{$table}: what was asked can not change' USING ERRCODE = 'insufficient_privilege';
                END IF;

                IF NEW.origin_institution_id IS DISTINCT FROM OLD.origin_institution_id
                    AND (OLD.origin_institution_id IS NOT NULL
                        OR OLD.status <> 'pending'
                        OR NEW.origin_institution_id IS DISTINCT FROM {$current}
                        OR NEW.institution_id = {$current}) THEN
                    RAISE EXCEPTION '{$table}: only the institution that answers sets itself as origin, once' USING ERRCODE = 'insufficient_privilege';
                END IF;

                RETURN NEW;
            END
            \$\$;
            CREATE TRIGGER guard_origin BEFORE UPDATE ON {$table} FOR EACH ROW EXECUTE FUNCTION guard_{$table}_origin();
            SQL);
    }

    /**
     * Back to the guard of ADR 0008 alone.
     */
    public static function unguardAddressedOrigin(string $table): void
    {
        $current = self::CURRENT_INSTITUTION_SQL;

        DB::statement("DROP TRIGGER guard_origin ON {$table}");
        DB::statement("DROP FUNCTION guard_{$table}_origin()");
        DB::statement("DROP POLICY addressed_insert ON {$table}");
        DB::statement("CREATE POLICY addressed_insert ON {$table} FOR INSERT WITH CHECK (institution_id = {$current})");
    }

    public static function disable(string $table): void
    {
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }

    /**
     * The opposite of enable(), for platform-wide tables that no institution
     * may ever see (e.g. the platform audit log, SPEC-005): while an
     * institution is active the table reads as empty and rejects every write.
     */
    public static function enableOutsideTenants(string $table): void
    {
        $current = self::CURRENT_INSTITUTION_SQL;

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY platform_only ON {$table}
            USING ({$current} IS NULL)
            WITH CHECK ({$current} IS NULL)
        ");
    }

    public static function disableOutsideTenants(string $table): void
    {
        DB::statement("DROP POLICY IF EXISTS platform_only ON {$table}");
        DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
    }

    /**
     * Scope the rest of the current transaction to the given institution
     * (ADR 0011): PostgreSQL forgets it at COMMIT or ROLLBACK, and undoes it
     * when a savepoint is rolled back, so no connection ever keeps it for
     * the next unit of work. Outside a transaction it would last a single
     * statement, so only the TenantActivator calls it, inside one.
     */
    public static function setInstitution(int $institutionId): void
    {
        DB::statement('select set_config(?, ?, true)', [self::SESSION_VARIABLE, (string) $institutionId]);
    }

    /**
     * Leave the institution for the rest of the current transaction: no
     * tenant-scoped row is visible or writable.
     */
    public static function clearInstitution(): void
    {
        DB::statement('select set_config(?, ?, true)', [self::SESSION_VARIABLE, '']);
    }

    /**
     * The institution the database applies right now, empty when none.
     */
    public static function currentSetting(): string
    {
        return (string) DB::scalar('select current_setting(?, true)', [self::SESSION_VARIABLE]);
    }
}
