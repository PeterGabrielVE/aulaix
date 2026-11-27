<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * SPEC-006 INS-R05.3, R05.4: who manages the institution's settings, in new
 * and existing institutions.
 */
function institutionPermissionsMigration(): object
{
    return require database_path('migrations/2026_10_20_100000_add_institution_permissions_to_existing_institutions.php');
}

test('existing institutions get gestionar-plantel for the Administrador only, and ver-plantel for the Director too', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);
    institutionPermissionsMigration()->down();

    expect(DB::table('permissions')->where('name', 'gestionar-plantel')->exists())->toBeFalse();

    institutionPermissionsMigration()->up();

    foreach ([
        'Administrador' => [true, true],
        'Director' => [false, true],
        'Coordinador' => [false, false],
        'Docente' => [false, false],
    ] as $role => [$manages, $views]) {
        expect(Role::findByName($role)->hasPermissionTo('gestionar-plantel'))->toBe($manages, "{$role} gestionar-plantel")
            ->and(Role::findByName($role)->hasPermissionTo('ver-plantel'))->toBe($views, "{$role} ver-plantel");
    }
});
