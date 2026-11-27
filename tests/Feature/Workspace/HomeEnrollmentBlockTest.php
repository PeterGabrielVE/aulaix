<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/AcademicCalendar/calendar_helpers.php';
require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-R09.3 (SPEC-013): the home counts the students enrolled in
 * the active year's sections, the coordinator's in its grades only, and
 * links to «Inscripciones».
 */
beforeEach(function () {
    $this->institution = tenant(['education_levels' => ['primaria']]);
    useTenant($this->institution);
    $this->year = storedSchoolYear(2026, 'active');
    $third = enrollmentSection($this->year, 'primaria_3');
    $fourth = enrollmentSection($this->year, 'primaria_4');
    enrollmentRow(enrollmentStudent('Pérez', 'Ana'), $this->year, $third);
    enrollmentRow(enrollmentStudent('Mora', 'Eva'), $this->year, $third);
    enrollmentRow(enrollmentStudent('Álvarez', 'Luis'), $this->year, $fourth, ['grade' => 'primaria_4']);
});

function enrolledIndicator(object $user, string $path): ?array
{
    $indicators = [];

    test()->actingAs($user)->get(tenantUrl(test()->institution, $path))->assertOk()
        ->assertInertia(function ($page) use (&$indicators) {
            $indicators = $page->toArray()['props']['home']['indicators'] ?? [];
        });

    return array_values(array_filter($indicators, fn (array $indicator) => $indicator['key'] === 'enrolled'))[0] ?? null;
}

it('the director sees how many students the sections of the active year have', function () {
    expect(enrolledIndicator(userWithRole($this->institution, 'Director'), '/director'))
        ->toBe(['key' => 'enrolled', 'value' => 3, 'href' => tenantUrl($this->institution, '/inscripciones')]);
});

it('the coordinator counts only those of the grades it coordinates', function () {
    $coordinator = userWithRole($this->institution, 'Coordinador');
    DB::table('coordinator_assignments')->insert(['school_year_id' => $this->year, 'account_id' => $coordinator->id, 'level' => 'primaria', 'grade' => 'primaria_4']);

    expect(enrolledIndicator($coordinator, '/coordinator')['value'])->toBe(1);
});
