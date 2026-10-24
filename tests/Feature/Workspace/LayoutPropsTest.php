<?php

require_once dirname(__DIR__, 2).'/Fixtures/AcademicCalendar/calendar_helpers.php';

/**
 * SPEC-013 NAV-R01.4, R02, R03.4 over HTTP: what every page of the
 * institution shares with the layout.
 */
beforeEach(function () {
    $this->institution = tenant(['name' => 'Colegio Norte']);
    $this->director = userWithRole($this->institution, 'Director', ['name' => 'Ana Rivas']);
});

function layoutOf(string $path = '/estructura'): array
{
    $layout = null;

    test()->actingAs(test()->director)->get(tenantUrl(test()->institution, $path))
        ->assertOk()
        ->assertInertia(function ($page) use (&$layout) {
            $layout = $page->toArray()['props']['layout'];
        });

    return $layout;
}

test('shows the active year, its open term, the person and their primary role', function () {
    storedSchoolYear(2026, 'active', closedTerms: 1);
    test()->director->assignRole('Docente');

    $layout = layoutOf();

    expect($layout['calendar'])->toBe(['year' => '2026-2027', 'term' => 2])
        ->and($layout['person'])->toBe(['name' => 'Ana Rivas', 'primaryRole' => 'Director'])
        ->and($layout['canSwitchInstitution'])->toBeFalse();
});

test('says there is no active year, or no open term', function () {
    $year = storedSchoolYear(2026);

    expect(layoutOf()['calendar'])->toBe(['year' => null, 'term' => null]);

    activateStoredSchoolYear($year);

    expect(layoutOf()['calendar'])->toBe(['year' => '2026-2027', 'term' => 1]);
});

test('groups the menu, with links and the current screen marked', function () {
    test()->director->assignRole('Docente');

    $navigation = layoutOf('/estructura')['navigation'];

    expect(array_map(fn (array $group) => [$group['group'], $group['label']], $navigation))->toBe([
        [null, null], ['academic', 'Académico'], ['students', 'Estudiantes'], ['institution', 'Plantel'],
    ])
        ->and(array_column($navigation[1]['entries'], 'key'))->toBe(['calendar', 'structure', 'my_assignments'])
        ->and($navigation[1]['entries'][1])->toBe([
            'key' => 'structure', 'label' => 'Estructura', 'href' => tenantUrl(test()->institution, '/estructura'), 'active' => true,
        ])
        ->and($navigation[0]['entries'][0]['active'])->toBeFalse();
});

test('marks the entry of a screen that depends on it', function () {
    $navigation = layoutOf('/users/create')['navigation'];
    $users = collect($navigation)->flatMap(fn (array $group) => $group['entries'])->firstWhere('key', 'users');

    expect($users['active'])->toBeTrue();
});

test('no longer shares the menu flags it replaces', function () {
    test()->actingAs(test()->director)->get(tenantUrl(test()->institution, '/estructura'))
        ->assertInertia(fn ($page) => $page->missing('auth.isGuardian')->missing('auth.isTeacher'));
});
