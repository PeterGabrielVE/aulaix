<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = ['gestionar-plantel', 'ver-plantel'];

    /**
     * SPEC-006 INS-R05.3, R05.4: gestionar-plantel for the Administrador and
     * ver-plantel for Administrador and Director, in every existing
     * institution. The seeder is idempotent.
     */
    public function up(): void
    {
        Institution::query()->each(
            fn (Institution $institution) => RolePermissionSeeder::seedForInstitution($institution)
        );

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
