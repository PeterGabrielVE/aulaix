<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = ['gestionar-calendario', 'editar-notas-lapso-cerrado'];

    /**
     * SPEC-007 CAL-R05.4, R06.5: gestionar-calendario for Administrador and
     * Director of every existing institution, and editar-notas-lapso-cerrado
     * created for no role at all. The seeder is idempotent.
     */
    public function up(): void
    {
        Institution::query()->each(
            fn (Institution $institution) => RolePermissionSeeder::seedForInstitution($institution)
        );

        // Also with no institution yet: the permissions exist from the start.
        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Removes both permissions, and every grant of them, from all institutions.
     */
    public function down(): void
    {
        Permission::query()->whereIn('name', self::PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
