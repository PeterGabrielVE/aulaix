<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Give Administrador and Director of every existing institution the new
     * ver-bitacora permission (SPEC-005 AUD-R06.3). The seeder is idempotent:
     * existing roles and assignments are left as they are.
     */
    public function up(): void
    {
        Institution::query()->each(
            fn (Institution $institution) => RolePermissionSeeder::seedForInstitution($institution)
        );
    }

    /**
     * Reverse the migrations. Removes the permission, and every grant of it,
     * from all institutions.
     */
    public function down(): void
    {
        Permission::query()->where('name', 'ver-bitacora')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
