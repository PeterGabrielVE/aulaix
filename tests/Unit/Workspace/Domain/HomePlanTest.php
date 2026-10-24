<?php

use AulaX\Workspace\Domain\Capabilities;
use AulaX\Workspace\Domain\HomeBlock;
use AulaX\Workspace\Domain\HomeKind;
use AulaX\Workspace\Domain\HomePlan;

/**
 * SPEC-013 NAV-R04, R05.5 – R05.7, R06.2, R09.1, R10.2: which blocks each
 * home screen carries, in order, and only the ones whose screen the person
 * may open.
 */
function planOf(HomeKind $kind, Capabilities $capabilities): array
{
    return array_map(fn (HomeBlock $block) => $block->value, HomePlan::for($kind, $capabilities));
}

const ALL_PERMISSIONS = ['gestionar-calendario', 'ver-estructura', 'ver-estudiantes', 'gestionar-estudiantes', 'ver-plantel', 'gestionar-plantel', 'gestionar-usuarios', 'ver-bitacora'];
const DIRECTOR_CAN = ['gestionar-calendario', 'ver-estructura', 'ver-estudiantes', 'gestionar-estudiantes', 'ver-plantel', 'gestionar-usuarios', 'ver-bitacora'];

it('gives the administration pending tasks, indicators, its operations, the recent log and shortcuts, in order', function () {
    expect(planOf(HomeKind::Admin, new Capabilities(ALL_PERMISSIONS, ['Administrador'], 'Administrador')))->toBe([
        'pending_year', 'pending_structure', 'pending_students', 'pending_invitations', 'pending_institution_data',
        'calendar', 'structure', 'students', 'staff',
        'recent_activity', 'shortcuts',
    ]);
});

it('gives the Director the academic blocks without the operations nor the log', function () {
    expect(planOf(HomeKind::Director, new Capabilities(DIRECTOR_CAN, ['Director'], 'Director')))->toBe([
        'pending_year', 'pending_structure', 'pending_students',
        'calendar', 'structure', 'students', 'staff',
        'shortcuts',
    ]);
});

it('leaves out every block whose screen the person may not open', function () {
    $withoutStudents = array_values(array_diff(DIRECTOR_CAN, ['ver-estudiantes', 'gestionar-estudiantes', 'gestionar-usuarios']));

    expect(planOf(HomeKind::Director, new Capabilities($withoutStudents, ['Director'], 'Director')))->toBe([
        'pending_year', 'pending_structure', 'calendar', 'structure', 'shortcuts',
    ]);
});

it('gives the Coordinator the structure of its grades, without grades to open sections in', function () {
    $plan = HomePlan::for(HomeKind::Coordinator, new Capabilities(['ver-estructura', 'ver-estudiantes'], ['Coordinador'], 'Coordinador'));

    expect(array_map(fn (HomeBlock $block) => $block->value, $plan))->toBe(['pending_structure', 'calendar', 'structure', 'shortcuts'])
        ->and(HomePlan::scopesToCoordinatedGrades(HomeKind::Coordinator))->toBeTrue()
        ->and(HomePlan::scopesToCoordinatedGrades(HomeKind::Director))->toBeFalse();
});

it('gives the Teacher its classes and the Guardian its students', function () {
    expect(planOf(HomeKind::Teacher, new Capabilities(['ver-estructura'], ['Docente'], 'Docente')))->toBe(['my_classes', 'shortcuts'])
        ->and(planOf(HomeKind::Guardian, new Capabilities([], ['Representante'], 'Representante', isGuardian: true)))->toBe(['my_students', 'shortcuts']);
});

it('gives the Guardian its block even before any student is linked', function () {
    expect(planOf(HomeKind::Guardian, new Capabilities([], ['Representante'], 'Representante')))->toBe(['my_students', 'shortcuts']);
});

it('adds the personal blocks of the other roles and links, once', function () {
    $directorWhoTeachesAndRepresents = new Capabilities(DIRECTOR_CAN, ['Director', 'Docente', 'Representante'], 'Director', isGuardian: true);

    expect(planOf(HomeKind::Director, $directorWhoTeachesAndRepresents))->toBe([
        'pending_year', 'pending_structure', 'pending_students',
        'calendar', 'structure', 'students', 'staff',
        'my_classes', 'my_students', 'shortcuts',
    ]);
});

it('tells the Student its content is still to come', function () {
    expect(planOf(HomeKind::Student, new Capabilities([], ['Estudiante'], 'Estudiante')))->toBe(['student_notice', 'shortcuts']);
});

it('knows the six home screens', function () {
    expect(array_map(fn (HomeKind $kind) => $kind->value, HomeKind::cases()))
        ->toBe(['admin', 'director', 'coordinator', 'teacher', 'guardian', 'student']);
});
