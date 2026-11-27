<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-R01 – R04, R09.1, R09.2, R14 (HTTP): the screens and forms
 * of enrollments, with the messages of each rule.
 */
beforeEach(function () {
    $this->institution = tenant();
    $this->admin = userWithRole($this->institution, 'Administrador');
    $this->year = enrollmentYear();
    $this->sectionA = enrollmentSection($this->year, letter: 'A', capacity: 1);
    $this->sectionB = enrollmentSection($this->year, letter: 'B');
    $this->fourth = enrollmentSection($this->year, grade: 'primaria_4');
    $this->ana = enrollmentStudent('Pérez', 'Ana');
    $this->luis = enrollmentStudent('Álvarez', 'Luis');
});

function enrollmentUrl(string $path = ''): string
{
    return tenantUrl(test()->institution, '/inscripciones'.$path);
}

function enrollOverHttp(array $data): TestResponse
{
    $test = test();

    return $test->actingAs($test->admin)->from(enrollmentUrl())->post(enrollmentUrl(), [
        'student' => $test->ana,
        'school_year' => $test->year,
        'section' => $test->sectionB,
        'date' => '2026-09-16',
        ...$data,
    ]);
}

it('shows the sections of the active year with their figures, and the students left to enroll', function () {
    enrollmentRow($this->luis, $this->year, $this->sectionA);

    $this->actingAs($this->admin)->get(enrollmentUrl())->assertOk()->assertInertia(fn ($page) => $page
        ->component('Enrollment/Index')
        ->where('overview.schoolYearId', $this->year)
        ->where('overview.status', 'active')
        ->where('overview.sections.0.letter', 'A')
        ->where('overview.sections.0.enrolled', 1)
        ->where('overview.sections.0.overCapacity', false)
        ->where('overview.unenrolled.0.fullName', 'Pérez, Ana')
        ->missing('overview.unenrolled.0.birthDate')
        ->where('canManage', true)
        ->where('canApprove', true));
});

it('enrolls a student, and says when the age is not the regular one', function () {
    enrollOverHttp([])->assertRedirect(enrollmentUrl())->assertSessionHas('success', 'Se inscribió al estudiante.');

    expect(DB::table('enrollments')->where('student_id', $this->ana)->value('section_id'))->toBe($this->sectionB);

    $young = enrollmentStudent('Mora', 'Eva', '2019-05-01');

    enrollOverHttp(['student' => $young])
        ->assertSessionHas('success', 'Se inscribió al estudiante. Tiene 7 años; la edad regular de 3.er grado es 8.');
});

it('says what each rule asks for', function () {
    enrollmentRow($this->luis, $this->year, $this->sectionA);

    enrollOverHttp(['section' => $this->sectionA])->assertSessionHasErrors(['over_capacity_reason' => 'La sección tiene 1 inscritos y cupo 1: indica el motivo para superarlo.']);
    enrollOverHttp(['student' => enrollmentStudent('Rojas', 'Paz', '2022-01-01')])->assertSessionHasErrors(['age_exception' => 'Tiene 4 años y el rango del grado es de 7 a 9 años: hace falta la excepción de un directivo.']);
    enrollOverHttp(['student' => enrollmentStudent('Díaz', 'Leo', status: 'withdrawn')])->assertSessionHasErrors(['student' => 'El estudiante está retirado: reincorpóralo primero.']);
    enrollOverHttp(['date' => '2099-01-01'])->assertSessionHasErrors(['date' => 'La fecha de inscripción no puede ser posterior a hoy.']);
    enrollOverHttp(['student' => $this->luis])->assertStatus(409);

    enrollOverHttp(['section' => $this->sectionA, 'over_capacity_reason' => 'Hermanos'])->assertSessionHasNoErrors();
});

it('lists the students of a section', function () {
    enrollmentRow($this->ana, $this->year, $this->sectionB);
    enrollmentRow($this->luis, $this->year, $this->sectionB);

    $this->actingAs($this->admin)->get(enrollmentUrl("/{$this->year}/secciones/{$this->sectionB}"))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Enrollment/Section')
        ->where('roster.section.letter', 'B')
        ->where('roster.students.0.fullName', 'Álvarez, Luis')
        ->where('roster.students.1.fullName', 'Pérez, Ana')
        ->has('sections', 3));

    $this->actingAs($this->admin)->get(enrollmentUrl("/{$this->year}/secciones/999999"))->assertNotFound();
});

it('moves a student to another section, or by a directive to another grade, and annuls an enrollment', function () {
    $enrollment = enrollmentRow($this->ana, $this->year, $this->sectionB);
    $sixth = enrollmentSection($this->year, grade: 'primaria_6');

    $this->actingAs($this->admin)->post(enrollmentUrl("/{$enrollment}/seccion"), ['section' => $this->sectionA, 'date' => '2026-10-01', 'reason' => 'Reorganización', 'over_capacity_reason' => null])
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Se cambió de sección.');
    $moved = DB::table('enrollments')->whereNull('ends_on')->where('student_id', $this->ana)->value('id');

    $this->actingAs($this->admin)->post(enrollmentUrl("/{$moved}/grado"), ['section' => $sixth, 'date' => '2026-10-02', 'reason' => 'Ubicación', 'age_exception' => null])
        ->assertSessionHasErrors(['age_exception']);
    $this->actingAs($this->admin)->post(enrollmentUrl("/{$moved}/grado"), ['section' => $sixth, 'date' => '2026-10-02', 'reason' => 'Ubicación', 'age_exception' => 'Evaluación psicopedagógica'])
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Se cambió de grado.');
    $current = DB::table('enrollments')->whereNull('ends_on')->where('student_id', $this->ana)->first();

    expect($current->section_id)->toBe($sixth)
        ->and($current->age_exception_by)->toBe($this->admin->id);

    $this->actingAs($this->admin)->post(enrollmentUrl("/{$current->id}/anular"), ['reason' => 'other', 'note' => ''])
        ->assertSessionHasErrors(['note' => 'Describe el motivo de la anulación.']);
    $this->actingAs($this->admin)->post(enrollmentUrl("/{$current->id}/anular"), ['reason' => 'duplicate'])
        ->assertSessionHasNoErrors()->assertSessionHas('success', 'Se anuló la inscripción.');

    expect(DB::table('enrollments')->whereNull('ends_on')->where('student_id', $this->ana)->exists())->toBeFalse();
});
