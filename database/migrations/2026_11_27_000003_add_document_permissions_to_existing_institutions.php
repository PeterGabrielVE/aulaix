<?php

use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel as Institution;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSION = 'emitir-documentos';

    /**
     * SPEC-012 DOC-R08.1: emitir-documentos for Administrador, Director and
     * Coordinador, in every existing institution. The seeder is idempotent.
     */
    public function up(): void
    {
        Institution::query()->each(
            fn (Institution $institution) => RolePermissionSeeder::seedForInstitution($institution)
        );

        Permission::findOrCreate(self::PERMISSION, 'web');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Removes emitir-documentos, and every grant of it, from all institutions.
     */
    public function down(): void
    {
        Permission::query()->where('name', self::PERMISSION)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
