<?php

use AulaX\Workspace\Application\CalendarSnapshot;
use AulaX\Workspace\Application\ShowHome\HomeView;
use AulaX\Workspace\Application\ShowHome\ShowHomeQuery;
use AulaX\Workspace\Application\Viewer;
use AulaX\Workspace\Domain\Capabilities;
use AulaX\Workspace\Domain\HomeKind;
use Tests\Fixtures\Workspace\FakeHomeSources;
use Tests\Fixtures\Workspace\FixedCalendarSource;
use Tests\Fixtures\Workspace\FixedViewerCapabilities;

/**
 * SPEC-013 NAV-R04 – R10.2: each home screen, with the data of the blocks
 * its plan carries and nothing else.
 */
const MANAGEMENT = ['gestionar-calendario', 'ver-estructura', 'ver-estudiantes', 'gestionar-estudiantes', 'ver-plantel', 'gestionar-plantel', 'gestionar-usuarios', 'ver-bitacora'];

beforeEach(function () {
    $this->sources = new FakeHomeSources;
    $this->calendar = new FixedCalendarSource(new CalendarSnapshot('2026-2027', 1, hasPlannedYear: false));
});

function homeOf(HomeKind $kind, Capabilities $capabilities): HomeView
{
    $test = test();

    return (new ShowHomeQuery(
        new FixedViewerCapabilities([5 => new Viewer($capabilities, 1)]),
        $test->calendar,
        $test->sources,
        $test->sources,
        $test->sources,
        $test->sources,
        $test->sources,
    ))(5, $kind);
}

function pendingKeys(HomeView $home): array
{
    return array_column($home->pending ?? [], 'count', 'key');
}

it('gives the Administrador pending tasks with their count and destination, without the ones at zero', function () {
    $home = homeOf(HomeKind::Admin, new Capabilities(MANAGEMENT, ['Administrador'], 'Administrador'));

    expect(pendingKeys($home))->toBe([
        'grades_without_sections' => 2, 'sections_without_guide' => 1, 'assignments_of_inactive_people' => 1,
        'pending_transfers' => 1, 'pending_invitations' => 2, 'expired_invitations' => 1, 'missing_dea_code' => 1,
    ])
        ->and(array_column($home->pending, 'target', 'key'))->toMatchArray([
            'sections_without_guide' => 'structure', 'pending_transfers' => 'transfers', 'expired_invitations' => 'users', 'missing_dea_code' => 'institution',
        ])
        ->and(array_column($home->indicators, 'value', 'key'))->toBe([
            'school_year' => '2026-2027', 'open_term' => 1, 'sections' => 3, 'capacity' => 90, 'enrolled' => 84, 'active_students' => 120,
            'staff' => ['Administrador' => 1, 'Director' => 1, 'Coordinador' => 2, 'Docente' => 12, 'Representante' => 80, 'Estudiante' => 0],
        ])
        ->and($home->recentActivity)->toHaveCount(1)
        ->and($home->shortcuts)->toBe(['calendar', 'structure', 'grades', 'students', 'enrollments', 'transfers', 'corrections', 'institution', 'users', 'audit']);
});

it('asks the Director nothing operational', function () {
    $home = homeOf(HomeKind::Director, new Capabilities(MANAGEMENT, ['Director'], 'Director'));

    expect(pendingKeys($home))->not->toHaveKeys(['pending_invitations', 'missing_dea_code'])
        ->and($home->recentActivity)->toBeNull()
        ->and($this->sources->asked)->not->toContain('invitations', 'gaps', 'activity');
});

it('says there are no pending tasks rather than showing an empty block', function () {
    $this->sources->overview = ['sections' => 3, 'capacity' => 90, 'gradesWithoutSections' => 0, 'sectionsWithoutGuide' => 0, 'subjectsWithoutTeacher' => 0, 'assignmentsOfInactivePeople' => 0, 'enrolled' => 0];
    $this->sources->requests = ['transfers' => 0, 'corrections' => 0];

    expect(homeOf(HomeKind::Director, new Capabilities(MANAGEMENT, ['Director'], 'Director'))->pending)->toBe([]);
});

it('asks for an active year when there is none, and counts no structure', function () {
    $this->calendar->snapshot = new CalendarSnapshot(null, null, hasPlannedYear: true);
    $this->sources->overview = null;

    $home = homeOf(HomeKind::Director, new Capabilities(MANAGEMENT, ['Director'], 'Director'));

    expect($home->pending[0])->toBe(['key' => 'no_active_year', 'count' => 1, 'target' => 'calendar', 'detail' => 'planned'])
        ->and(array_column($home->indicators, 'key'))->not->toContain('sections');
});

it('fetches nothing for blocks the person may not see', function () {
    homeOf(HomeKind::Director, new Capabilities(['ver-estructura'], ['Director'], 'Director'));

    expect($this->sources->asked)->toBe(['overview']);
});

it('gives the Coordinator the figures of its grades', function () {
    $this->sources->coordinated[5] = ['primaria_1', 'primaria_2'];

    $home = homeOf(HomeKind::Coordinator, new Capabilities(['ver-estructura'], ['Coordinador'], 'Coordinador'));

    expect($this->sources->overviewGrades)->toBe(['primaria_1', 'primaria_2'])
        ->and(pendingKeys($home))->toBe(['sections_without_guide' => 1, 'assignments_of_inactive_people' => 1])
        ->and(array_column($home->indicators, 'target', 'key'))->toMatchArray(['school_year' => null, 'sections' => 'structure']);
});

it('tells a Coordinator without grades, or without an active year, instead of figures', function () {
    $home = homeOf(HomeKind::Coordinator, new Capabilities(['ver-estructura'], ['Coordinador'], 'Coordinador'));

    expect($home->notices)->toBe(['no_coordinated_grades'])
        ->and($home->pending)->toBeNull()
        ->and($this->sources->asked)->not->toContain('overview');

    $this->calendar->snapshot = new CalendarSnapshot(null, null, false);
    $this->sources->coordinated[5] = ['primaria_1'];

    expect(homeOf(HomeKind::Coordinator, new Capabilities(['ver-estructura'], ['Coordinador'], 'Coordinador'))->notices)->toBe(['no_active_year']);
});

it('gives a Teacher its classes, and a Director who teaches them too', function () {
    expect(homeOf(HomeKind::Teacher, new Capabilities([], ['Docente'], 'Docente'))->myClasses)->toBe($this->sources->classes)
        ->and(homeOf(HomeKind::Director, new Capabilities(['ver-estructura'], ['Director', 'Docente'], 'Director'))->myClasses)->toBe($this->sources->classes);
});

it('gives a Guardian its students and its corrections waiting', function () {
    $home = homeOf(HomeKind::Guardian, new Capabilities([], ['Representante'], 'Representante', isGuardian: true));

    expect($home->myStudents)->toBe($this->sources->represented)
        ->and($home->myClasses)->toBeNull();
});

it('tells the Student its content is to come', function () {
    $home = homeOf(HomeKind::Student, new Capabilities([], ['Estudiante'], 'Estudiante'));

    expect($home->notices)->toBe(['student'])
        ->and($home->pending)->toBeNull()
        ->and($home->indicators)->toBeNull()
        ->and($this->sources->asked)->toBe([]);
});
