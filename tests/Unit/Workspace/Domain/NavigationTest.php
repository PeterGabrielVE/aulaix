<?php

use AulaX\Workspace\Domain\Capabilities;
use AulaX\Workspace\Domain\Navigation;

/**
 * SPEC-013 NAV-R03.1, R03.2: the menu each person sees, grouped and in
 * order, adding up all their roles and links.
 */
function menuOf(Capabilities $capabilities): array
{
    return array_map(
        fn (array $group) => [$group['group']?->value => array_map(fn ($entry) => $entry->value, $group['entries'])],
        Navigation::for($capabilities),
    );
}

const DIRECTOR_PERMISSIONS = ['gestionar-usuarios', 'ver-estudiantes', 'ver-bitacora', 'gestionar-calendario', 'ver-plantel', 'gestionar-estudiantes', 'gestionar-estructura', 'ver-estructura'];

it('groups the entries of a Director, without the ones of other roles', function () {
    expect(menuOf(new Capabilities(DIRECTOR_PERMISSIONS, ['Director'], 'Director')))->toBe([
        ['' => ['home']],
        ['academic' => ['calendar', 'structure', 'grades']],
        ['students' => ['students', 'enrollments', 'transfers', 'corrections']],
        ['institution' => ['institution', 'users', 'audit']],
    ]);
});

it('adds the entries of every role and link a person has', function () {
    $directorWhoTeachesAndIsAGuardian = new Capabilities(DIRECTOR_PERMISSIONS, ['Director', 'Docente'], 'Director', isGuardian: true);

    expect(menuOf($directorWhoTeachesAndIsAGuardian))->toBe([
        ['' => ['home']],
        ['academic' => ['calendar', 'structure', 'my_assignments', 'grades']],
        ['students' => ['students', 'enrollments', 'transfers', 'corrections', 'my_students']],
        ['institution' => ['institution', 'users', 'audit']],
    ]);
});

it('shows a teacher its classes and what it may consult', function () {
    expect(menuOf(new Capabilities(['ver-estudiantes', 'ver-estructura'], ['Docente'], 'Docente')))->toBe([
        ['' => ['home']],
        ['academic' => ['structure', 'my_assignments']],
        ['students' => ['students', 'enrollments']],
    ]);
});

it('hides the groups without entries for a guardian', function () {
    expect(menuOf(new Capabilities([], ['Representante'], 'Representante', isGuardian: true)))->toBe([
        ['' => ['home']],
        ['students' => ['my_students']],
    ]);
});

it('leaves only the home entry to a member without roles', function () {
    expect(menuOf(new Capabilities([], [], null)))->toBe([['' => ['home']]]);
});
