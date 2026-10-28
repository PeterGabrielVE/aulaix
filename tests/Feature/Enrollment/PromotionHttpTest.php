<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';
require_once dirname(__DIR__, 2).'/Fixtures/AcademicCalendar/calendar_helpers.php';

/**
 * SPEC-010 MAT-R06 (HTTP): the results of a section, recorded once the
 * third term is closed.
 */
beforeEach(function () {
    $this->institution = tenant();
    $this->admin = userWithRole($this->institution, 'Administrador');
    $this->year = storedSchoolYear(2026, 'active', closedTerms: 3);
    $this->section = enrollmentSection($this->year);
    $this->ana = enrollmentRow(enrollmentStudent('Pérez', 'Ana'), $this->year, $this->section);
    $this->luis = enrollmentRow(enrollmentStudent('Álvarez', 'Luis'), $this->year, $this->section);
});

it('shows the results of a section, who has one and what the level allows', function () {
    DB::table('enrollments')->where('id', $this->ana)->update(['outcome' => 'repeats', 'outcome_recorded_at' => now(), 'outcome_recorded_by' => 1]);

    $this->actingAs($this->admin)->get(tenantUrl($this->institution, "/prosecucion/{$this->year}/secciones/{$this->section}"))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Enrollment/Promotion')
        ->where('sheet.ready', true)
        ->where('sheet.students.0.fullName', 'Álvarez, Luis')
        ->where('sheet.students.0.outcome', null)
        ->where('sheet.students.1.outcome', 'repeats')
        ->where('sheet.outcomes', ['promoted', 'repeats', 'lost_by_absence'])
        ->has('outcomeLabels'));
});

it('records the result of a student, and says what the level does not allow', function () {
    $url = tenantUrl($this->institution, "/inscripciones/{$this->ana}/resultado");

    $this->actingAs($this->admin)->put($url, ['outcome' => 'promoted_with_pending', 'pending_subjects' => [1]])
        ->assertSessionHasErrors(['outcome' => 'En Primaria no hay materias pendientes: el estudiante es promovido o repite.']);
    $this->actingAs($this->admin)->put($url, ['outcome' => 'promoted'])
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Se guardó el resultado.');

    expect(DB::table('enrollments')->where('id', $this->ana)->value('outcome'))->toBe('promoted');
});

it('is only for whoever manages enrollments', function () {
    $teacher = userWithRole($this->institution, 'Docente');

    $this->actingAs($teacher)->get(tenantUrl($this->institution, "/prosecucion/{$this->year}/secciones/{$this->section}"))->assertForbidden();
    $this->actingAs($teacher)->put(tenantUrl($this->institution, "/inscripciones/{$this->ana}/resultado"), ['outcome' => 'promoted'])->assertForbidden();
});
