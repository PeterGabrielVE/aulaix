<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * SPEC-010 MAT-R11.2, R11.5: who enrolls and who approves the exceptions,
 * in new and existing institutions.
 */
function enrollmentPermissionsMigration(): object
{
    $migrations = glob(database_path('migrations/*_add_enrollment_permissions_to_existing_institutions.php'));

    return require $migrations[0];
}

test('existing institutions get gestionar-inscripciones and aprobar-excepciones-inscripcion for Administrador and Director only', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);
    enrollmentPermissionsMigration()->down();

    expect(DB::table('permissions')->whereIn('name', ['gestionar-inscripciones', 'aprobar-excepciones-inscripcion'])->exists())->toBeFalse();

    enrollmentPermissionsMigration()->up();

    foreach ([
        'Administrador' => true,
        'Director' => true,
        'Coordinador' => false,
        'Docente' => false,
        'Representante' => false,
    ] as $role => $granted) {
        expect(Role::findByName($role)->hasPermissionTo('gestionar-inscripciones'))->toBe($granted, "{$role} gestionar-inscripciones")
            ->and(Role::findByName($role)->hasPermissionTo('aprobar-excepciones-inscripcion'))->toBe($granted, "{$role} aprobar-excepciones-inscripcion");
    }
});

test('new institutions get them too', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);

    expect(Role::findByName('Director')->hasPermissionTo('gestionar-inscripciones'))->toBeTrue()
        ->and(Role::findByName('Administrador')->hasPermissionTo('aprobar-excepciones-inscripcion'))->toBeTrue()
        ->and(Role::findByName('Docente')->hasPermissionTo('gestionar-inscripciones'))->toBeFalse();
});
