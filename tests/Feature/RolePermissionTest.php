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

test('every institution gets the six base roles', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);

    expect(Role::where('institution_id', $institution->id)->pluck('name')->sort()->values()->all())
        ->toBe(['Administrador', 'Coordinador', 'Director', 'Docente', 'Estudiante', 'Representante']);
});

test('each base role carries its permissions', function (string $role, array $granted, array $denied) {
    $institution = tenant();
    $user = userWithRole($institution, $role);

    foreach ($granted as $permission) {
        expect($user->hasPermissionTo($permission))->toBeTrue("{$role} should be able to {$permission}");
    }

    foreach ($denied as $permission) {
        expect($user->hasPermissionTo($permission))->toBeFalse("{$role} should not be able to {$permission}");
    }
})->with([
    'Director' => ['Director', ['gestionar-usuarios', 'ver-estudiantes', 'ver-calificaciones'], ['gestionar-roles', 'gestionar-calificaciones']],
    'Coordinador' => ['Coordinador', ['ver-estudiantes', 'gestionar-calificaciones', 'ver-calificaciones'], ['gestionar-usuarios', 'gestionar-roles']],
]);

test('the same person can hold different roles in each institution', function () {
    $institutionA = tenant();
    $teacherInA = userWithRole($institutionA, 'Docente', ['email' => 'compartido@example.com']);

    $institutionB = tenant();
    $guardianInB = userWithRole($institutionB, 'Representante', ['email' => 'compartido@example.com']);

    $this->actingAs($teacherInA)->get(tenantUrl($institutionA, '/dashboard'))
        ->assertRedirect(route('teacher.home'));
    $this->actingAs($teacherInA)->get(tenantUrl($institutionA, '/guardian'))->assertForbidden();

    $this->actingAs($guardianInB)->get(tenantUrl($institutionB, '/dashboard'))
        ->assertRedirect(route('guardian.home'));
    $this->actingAs($guardianInB)->get(tenantUrl($institutionB, '/teacher'))->assertForbidden();
});

test('the roles migration adds director and coordinador to existing institutions', function () {
    $institution = tenant();
    RolePermissionSeeder::seedForInstitution($institution);
    $admin = User::factory()->for($institution)->create();
    $admin->assignRole('Administrador');
    Role::whereIn('name', ['Director', 'Coordinador'])->delete();

    $migration = require database_path('migrations/2026_10_09_215951_add_director_and_coordinator_roles_to_existing_institutions.php');
    $migration->up();

    expect(Role::where('institution_id', $institution->id)->whereIn('name', ['Director', 'Coordinador'])->count())->toBe(2);
    expect($admin->fresh()->getRoleNames()->all())->toBe(['Administrador']);
});
