<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tenant-scoped tables get PostgreSQL Row Level Security (F1-02), on top
     * of (not instead of) the Eloquent global scope in
     * App\Concerns\BelongsToInstitution. The Eloquent scope is a convenience;
     * RLS is the real security boundary enforced by the database itself,
     * even against raw SQL, a forgotten ->withoutGlobalScope(), or a bug.
     *
     * FORCE ROW LEVEL SECURITY matters here: the app's DB role
     * (aulaix_app) owns these tables so it can run migrations, and Postgres
     * exempts table owners from RLS by default. FORCE removes that
     * exemption, so the policy applies to every session, including the
     * owner — see docker/postgres/init.sql for why aulaix_app is not a
     * superuser (superusers can never be forced to obey RLS).
     */
    private array $tenantTables = [
        'users',
    ];

    public function up(): void
    {
        foreach ($this->tenantTables as $table) {
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                USING (institution_id = current_setting('app.current_institution_id', true)::bigint)
                WITH CHECK (institution_id = current_setting('app.current_institution_id', true)::bigint)
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tenantTables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} NO FORCE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
