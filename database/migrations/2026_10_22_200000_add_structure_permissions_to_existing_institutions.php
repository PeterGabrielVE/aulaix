<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = ['gestionar-estructura', 'ver-estructura'];

    /**
     * SPEC-008 EAC-R10.2, R10.3: gestionar-estructura for Administrador and
     * Director, ver-estructura for them and Coordinador and Docente, in every
     * existing institution. The seeder is idempotent.
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
