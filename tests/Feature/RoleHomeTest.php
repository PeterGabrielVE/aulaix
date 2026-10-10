<?php

use App\Models\User;

dataset('role homes', [
    'Administrador' => ['Administrador', 'admin.home', '/admin', 'Home/Admin'],
    'Director' => ['Director', 'director.home', '/director', 'Home/Director'],
    'Coordinador' => ['Coordinador', 'coordinator.home', '/coordinator', 'Home/Coordinator'],
    'Docente' => ['Docente', 'teacher.home', '/teacher', 'Home/Teacher'],
    'Representante' => ['Representante', 'guardian.home', '/guardian', 'Home/Guardian'],
    'Estudiante' => ['Estudiante', 'student.home', '/student', 'Home/Student'],
]);

test('each role is sent to its own home screen after login', function (string $role, string $routeName, string $path, string $component) {
    $institution = tenant();
    $user = userWithRole($institution, $role);

    $this->post(tenantUrl($institution, '/login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(tenantUrl($institution, '/dashboard'))->assertRedirect(route($routeName));

    $this->get(tenantUrl($institution, $path))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with('role homes');

test('a user with several roles lands on the highest-priority one', function () {
    $institution = tenant();
    $user = userWithRole($institution, 'Estudiante');
    $user->assignRole('Docente');

    $this->actingAs($user)->get(tenantUrl($institution, '/dashboard'))
        ->assertRedirect(route('teacher.home'));
});

test('a director who also teaches lands on the director home', function () {
    $institution = tenant();
    $user = userWithRole($institution, 'Docente');
    $user->assignRole('Director');

    $this->actingAs($user)->get(tenantUrl($institution, '/dashboard'))
        ->assertRedirect(route('director.home'));
});

test('a user without a role sees a notice instead of a home screen', function () {
    $institution = tenant();
    $user = User::factory()->for($institution)->create();

    $this->actingAs($user)->get(tenantUrl($institution, '/dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Home/NoRole'));
});

test("a role can not open another role's home screen", function () {
    $institution = tenant();
    $student = userWithRole($institution, 'Estudiante');

    $this->actingAs($student)->get(tenantUrl($institution, '/admin'))->assertForbidden();
    $this->actingAs($student)->get(tenantUrl($institution, '/teacher'))->assertForbidden();
    $this->actingAs($student)->get(tenantUrl($institution, '/director'))->assertForbidden();
    $this->actingAs($student)->get(tenantUrl($institution, '/coordinator'))->assertForbidden();
});
