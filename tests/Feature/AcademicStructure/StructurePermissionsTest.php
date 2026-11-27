<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * SPEC-008 EAC-R10.2, R10.3: who manages and who sees the structure, in new
 * and existing institutions.
 */
function structurePermissionsMigration(): object
{
    return require database_path('migrations/2026_10_22_200000_add_structure_permissions_to_existing_institutions.php');
}

test('existing institutions get gestionar-estructura for Administrador and Director, and ver-estructura for teachers and coordinators too', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);
    structurePermissionsMigration()->down();

    expect(DB::table('permissions')->whereIn('name', ['gestionar-estructura', 'ver-estructura'])->exists())->toBeFalse();

    structurePermissionsMigration()->up();

    foreach ([
        'Administrador' => [true, true],
        'Director' => [true, true],
        'Coordinador' => [false, true],
        'Docente' => [false, true],
        'Representante' => [false, false],
    ] as $role => [$manages, $views]) {
        expect(Role::findByName($role)->hasPermissionTo('gestionar-estructura'))->toBe($manages, "{$role} gestionar-estructura")
            ->and(Role::findByName($role)->hasPermissionTo('ver-estructura'))->toBe($views, "{$role} ver-estructura");
    }
});
