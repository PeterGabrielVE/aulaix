<?php

use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\IdentityAccess\Domain\Role\HomeScreen;
use AulaX\IdentityAccess\Domain\Role\Permission;
use AulaX\IdentityAccess\Domain\Role\RoleSet;

it('lands a user on the home screen of their highest-priority role', function (array $roles, HomeScreen $home) {
    expect(RoleSet::of(...$roles)->homeScreen())->toBe($home);
})->with([
    'a single role' => [['Docente'], HomeScreen::Teacher],
    'director who also teaches' => [['Docente', 'Director'], HomeScreen::Director],
    'administrador above all' => [['Estudiante', 'Representante', 'Administrador'], HomeScreen::Admin],
    'guardian above student' => [['Estudiante', 'Representante'], HomeScreen::Guardian],
]);

it('has no home screen without a base role', function () {
    expect(RoleSet::of()->homeScreen())->toBeNull();
});

it('ignores repeated role names', function () {
    expect(RoleSet::of('Docente', 'Docente', 'Representante')->names())->toBe(['Docente', 'Representante']);
});

it('lists its roles in priority order, unknown names last', function () {
    expect(RoleSet::of('Estudiante', 'Auditor externo', 'Director')->inPriorityOrder())->toBe(['Director', 'Estudiante', 'Auditor externo']);
});

test('each base role carries the permissions its specs give it', function (BaseRole $role, array $permissions) {
    expect(array_map(fn (Permission $permission) => $permission->value, $role->permissions()))->toEqualCanonicalizing($permissions);
})->with([
    'Administrador' => [BaseRole::Administrador, ['gestionar-usuarios', 'gestionar-roles', 'ver-estudiantes', 'gestionar-calificaciones', 'ver-calificaciones', 'ver-bitacora', 'gestionar-calendario', 'gestionar-plantel', 'ver-plantel', 'gestionar-estudiantes', 'gestionar-estructura', 'ver-estructura', 'gestionar-inscripciones', 'aprobar-excepciones-inscripcion']],
    'Director' => [BaseRole::Director, ['gestionar-usuarios', 'ver-estudiantes', 'ver-calificaciones', 'ver-bitacora', 'gestionar-calendario', 'ver-plantel', 'gestionar-estudiantes', 'gestionar-estructura', 'ver-estructura', 'gestionar-inscripciones', 'aprobar-excepciones-inscripcion']],
    'Coordinador' => [BaseRole::Coordinador, ['ver-estudiantes', 'gestionar-calificaciones', 'ver-calificaciones', 'ver-estructura']],
    'Docente' => [BaseRole::Docente, ['ver-estudiantes', 'gestionar-calificaciones', 'ver-calificaciones', 'ver-estructura']],
    'Representante' => [BaseRole::Representante, ['ver-calificaciones']],
    'Estudiante' => [BaseRole::Estudiante, ['ver-calificaciones']],
]);

test('no base role carries the permission to change grades of closed terms', function () {
    foreach (BaseRole::cases() as $role) {
        expect($role->permissions())->not->toContain(Permission::EditClosedTermGrades);
    }
});
