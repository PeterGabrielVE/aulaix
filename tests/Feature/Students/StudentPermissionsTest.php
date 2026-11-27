<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * SPEC-009 EST-R09.2: who manages students, in new and existing institutions.
 */
function studentPermissionsMigration(): object
{
    return require database_path('migrations/2026_10_21_100000_add_student_permissions_to_existing_institutions.php');
}

test('existing institutions get gestionar-estudiantes for Administrador and Director only', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);
    studentPermissionsMigration()->down();

    expect(DB::table('permissions')->where('name', 'gestionar-estudiantes')->exists())->toBeFalse();

    studentPermissionsMigration()->up();

    foreach (['Administrador' => true, 'Director' => true, 'Coordinador' => false, 'Docente' => false, 'Representante' => false] as $role => $manages) {
        expect(Role::findByName($role)->hasPermissionTo('gestionar-estudiantes'))->toBe($manages, $role);
    }
});
