<?php

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';
require_once dirname(__DIR__, 2).'/Fixtures/AcademicCalendar/calendar_helpers.php';

/**
 * SPEC-010 MAT-R16.1, R16.2: before closing the year, who still lacks its
 * result, by section; and the year does not close until then.
 */
beforeEach(function () {
    $this->institution = tenant();
    $this->year = storedSchoolYear(2026, 'active', closedTerms: 3);
    $section = enrollmentSection($this->year);
    enrollmentRow(enrollmentStudent('Pérez', 'Ana'), $this->year, $section);
});

it('the calendar shows, for the active year, who still lacks its result', function () {
    $admin = userWithRole($this->institution, 'Administrador');

    $this->actingAs($admin)->get(tenantUrl($this->institution, '/calendario'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('pendingResults.0.gradeName', '3.er grado')
        ->where('pendingResults.0.letter', 'A')
        ->where('pendingResults.0.count', 1)
        ->where('pendingResults.0.students', ['Pérez, Ana']));

    $this->actingAs($admin)->post(tenantUrl($this->institution, "/calendario/anios/{$this->year}/cerrar"), ['confirmation' => true])
        ->assertSessionHasErrors(['status' => 'Hay 1 estudiantes sin resultado de prosecución: regístralo o retíralos antes de cerrar el año.']);
});
