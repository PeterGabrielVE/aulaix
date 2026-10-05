<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Role;

test('the same role name exists independently per institution', function () {
    $institutionA = tenant();
    RolePermissionSeeder::seedForInstitution($institutionA);

    $institutionB = tenant();
    RolePermissionSeeder::seedForInstitution($institutionB);

    // The Role model has no global tenant scope of its own (unlike our
    // BelongsToInstitution models) — this is a plain, unscoped query.
    $rolesNamedDocente = Role::where('name', 'Docente')->get();

    expect($rolesNamedDocente)->toHaveCount(2);
    expect($rolesNamedDocente->pluck('institution_id')->sort()->values()->all())
        ->toBe(collect([$institutionA->id, $institutionB->id])->sort()->values()->all());
});

test('a user only has the permissions granted within their own institution', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);

    $teacher = User::factory()->for($institution)->create();
    $teacher->assignRole('Docente');

    expect($teacher->hasPermissionTo('gestionar-calificaciones'))->toBeTrue();
    expect($teacher->hasPermissionTo('gestionar-usuarios'))->toBeFalse();
});

test('assigning a role in one institution does not grant it in another', function () {
    $institutionA = tenant();
    RolePermissionSeeder::seedForInstitution($institutionA);
    $adminA = User::factory()->for($institutionA)->create();
    $adminA->assignRole('Administrador');

    $institutionB = tenant();
    RolePermissionSeeder::seedForInstitution($institutionB);
    $memberB = User::factory()->for($institutionB)->create();

    // Still "in" institution B's team context: memberB was never assigned
    // any role there, even though a same-named role exists for institution A.
    expect($memberB->hasRole('Administrador'))->toBeFalse();
});
