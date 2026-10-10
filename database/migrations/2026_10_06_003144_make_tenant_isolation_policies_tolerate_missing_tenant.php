<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A query with no tenant must return no rows, not fail. Once a session
     * has defined app.current_institution_id, clearing it (set_config to ''
     * or RESET) leaves it as '' rather than NULL, and the original policies'
     * ''::bigint cast raised "invalid input syntax for type bigint" on every
     * query. nullif() turns '' into NULL, and institution_id = NULL matches
     * nothing — the same result as a session that never had a tenant.
     */
    private array $tenantTables = [
        'users',
        'password_reset_tokens',
    ];

    public function up(): void
    {
        $this->recreatePolicies("nullif(current_setting('app.current_institution_id', true), '')::bigint");
    }

    public function down(): void
    {
        $this->recreatePolicies("current_setting('app.current_institution_id', true)::bigint");
    }

    private function recreatePolicies(string $currentInstitution): void
    {
        foreach ($this->tenantTables as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("
                CREATE POLICY tenant_isolation ON {$table}
                USING (institution_id = {$currentInstitution})
                WITH CHECK (institution_id = {$currentInstitution})
            ");
        }
    }
};
