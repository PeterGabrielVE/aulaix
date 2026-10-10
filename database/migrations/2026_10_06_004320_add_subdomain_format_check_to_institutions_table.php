<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A subdomain becomes a hostname ({subdomain}.APP_DOMAIN), so it must be
     * a single lower-case DNS label: letters, digits and inner hyphens, at
     * most 63 characters. A dot would also make it unreachable, since the
     * {tenant} route segment never matches one. The platform's own
     * subdomains (Institution::RESERVED_SUBDOMAINS when this ran) are
     * excluded so an institution can never shadow them.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE institutions ADD CONSTRAINT institutions_subdomain_format CHECK (
                subdomain ~ '^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$'
                AND subdomain NOT IN ('www', 'api', 'admin', 'app', 'mail', 'static', 'assets', 'cdn')
            )
        ");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE institutions DROP CONSTRAINT institutions_subdomain_format');
    }
};
