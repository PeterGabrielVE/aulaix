<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;

require_once dirname(__DIR__, 2).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-R11.1, R11.3 – R11.5, NF01: who sees, who enrolls and who
 * approves the exceptions, in the route and again in the use case.
 */
beforeEach(function () {
    $this->institution = tenant();
    $this->year = enrollmentYear();
    $this->section = enrollmentSection($this->year);
    $this->fourth = enrollmentSection($this->year, grade: 'primaria_4');
    $this->student = enrollmentStudent();
});

function enrollAs(object $user): TestResponse
{
    $test = test();

    return $test->actingAs($user)->post(tenantUrl($test->institution, '/inscripciones'), [
        'student' => $test->student, 'school_year' => $test->year, 'section' => $test->section, 'date' => '2026-09-16',
    ]);
}

it('a teacher sees the enrollments but does not enroll, move nor annul', function () {
    $teacher = userWithRole($this->institution, 'Docente');
    $enrollment = enrollmentRow($this->student, $this->year, $this->section);

    $this->actingAs($teacher)->get(tenantUrl($this->institution, '/inscripciones'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('canManage', false)->where('canApprove', false));
    enrollAs($teacher)->assertForbidden();
    $this->actingAs($teacher)->post(tenantUrl($this->institution, "/inscripciones/{$enrollment}/seccion"), [])->assertForbidden();
    $this->actingAs($teacher)->post(tenantUrl($this->institution, "/inscripciones/{$enrollment}/grado"), [])->assertForbidden();
    $this->actingAs($teacher)->post(tenantUrl($this->institution, "/inscripciones/{$enrollment}/anular"), [])->assertForbidden();

    expect(DB::table('enrollments')->count())->toBe(1);
});

it('whoever does not see students does not see the enrollments', function () {
    $guardian = userWithRole($this->institution, 'Representante');

    $this->actingAs($guardian)->get(tenantUrl($this->institution, '/inscripciones'))->assertForbidden();
});

it('a member granted gestionar-inscripciones enrolls, but a change of grade stays a directive\'s', function () {
    $secretary = userWithRole($this->institution, 'Docente');
    $admin = userWithRole($this->institution, 'Administrador');
    $this->actingAs($admin)->put(tenantUrl($this->institution, "/users/{$secretary->id}/permiso-inscripciones"), ['allowed' => true]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    auth()->forgetGuards();

    enrollAs($secretary)->assertSessionHasNoErrors();
    $enrollment = DB::table('enrollments')->value('id');

    $this->actingAs($secretary)->post(tenantUrl($this->institution, "/inscripciones/{$enrollment}/grado"), ['section' => $this->fourth, 'date' => '2026-10-01', 'reason' => 'Ubicación'])
        ->assertForbidden();
});

it('a director enrolls and approves the exceptions', function () {
    $director = userWithRole($this->institution, 'Director');

    enrollAs($director)->assertSessionHasNoErrors();
    $enrollment = DB::table('enrollments')->value('id');

    $this->actingAs($director)->post(tenantUrl($this->institution, "/inscripciones/{$enrollment}/grado"), ['section' => $this->fourth, 'date' => '2026-10-01', 'reason' => 'Ubicación', 'age_exception' => 'Evaluación'])
        ->assertSessionHasNoErrors();
});
