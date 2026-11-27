<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-R07 (HTTP): the proposal for the next year and its
 * confirmation.
 */
beforeEach(function () {
    $this->institution = tenant();
    $this->admin = userWithRole($this->institution, 'Administrador');
    $this->year = enrollmentYear(2026);
    $this->next = enrollmentYear(2027, 'planned');
    $this->third = enrollmentSection($this->year, grade: 'primaria_3');
    $this->fourth = enrollmentSection($this->next, grade: 'primaria_4');
    $this->ana = enrollmentStudent('Pérez', 'Ana');
    $this->luis = enrollmentStudent('Álvarez', 'Luis');
    enrollmentRow($this->ana, $this->year, $this->third, ['outcome' => 'promoted', 'outcome_recorded_at' => now(), 'outcome_recorded_by' => 1]);
    enrollmentRow($this->luis, $this->year, $this->third);
});

it('shows the proposal: who goes where, and who is left out and why', function () {
    $this->actingAs($this->admin)->get(tenantUrl($this->institution, "/prosecucion/{$this->year}/lote"))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Enrollment/NextYear')
        ->where('proposal.toSchoolYearId', $this->next)
        ->where('proposal.entries.0.fullName', 'Pérez, Ana')
        ->where('proposal.entries.0.sectionIds', [$this->fourth])
        ->where('proposal.excluded.0.fullName', 'Álvarez, Luis')
        ->where('proposal.excluded.0.reason', 'No tiene resultado de prosecución.')
        ->has('proposal.sections', 1));
});

it('enrolls the chosen students in the next year', function () {
    $this->actingAs($this->admin)->from(tenantUrl($this->institution, "/prosecucion/{$this->year}/lote"))
        ->post(tenantUrl($this->institution, "/prosecucion/{$this->year}/lote"), ['placements' => [$this->ana => $this->fourth]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(tenantUrl($this->institution, "/inscripciones?anio={$this->next}"))
        ->assertSessionHas('success', 'Se inscribieron 1 estudiantes en el año siguiente.');

    expect(DB::table('enrollments')->where('school_year_id', $this->next)->where('student_id', $this->ana)->value('section_id'))->toBe($this->fourth);
});

it('says when there is no next year', function () {
    $this->actingAs($this->admin)->from(tenantUrl($this->institution, '/inscripciones'))
        ->get(tenantUrl($this->institution, "/prosecucion/{$this->next}/lote"))
        ->assertRedirect(tenantUrl($this->institution, '/inscripciones'))
        ->assertSessionHasErrors(['school_year' => 'No hay un año escolar siguiente.']);
});
