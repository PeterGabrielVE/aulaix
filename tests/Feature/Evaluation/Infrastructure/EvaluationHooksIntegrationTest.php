<?php

use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentCommand;
use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentHandler;
use AulaX\Enrollment\Domain\AnnulmentNotAllowed;
use AulaX\Evaluation\Application\Gradebook\GradebookQuery;
use AulaX\Evaluation\Application\SaveActivityGrades\SaveActivityGradesHandler;
use AulaX\Evaluation\Domain\GradeNotValid;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

require_once dirname(__DIR__, 3).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-R09.1 – R09.3 with the real modules (ADR 0012): what
 * Enrollment and AcademicStructure ask Evaluation, and what a change of
 * grade does to the grades.
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $this->math), ['date' => '2026-10-05']);
    activityGradeRow($this->activity, $this->luis, ['score' => 800]);
    $this->admin = userWithRole($this->institution, 'Administrador');
});

it('an enrollment with grades is annulled only by a manager', function () {
    $secretary = userWithRole($this->institution, 'Docente');
    $this->actingAs($this->admin)->put(tenantUrl($this->institution, "/users/{$secretary->id}/permiso-inscripciones"), ['allowed' => true]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    auth()->forgetGuards();
    $graded = (int) DB::table('enrollments')->where('student_id', $this->luis)->value('id');
    $this->actingAs($secretary);

    expect(fn () => app(AnnulEnrollmentHandler::class)(new AnnulEnrollmentCommand($graded, 'duplicate', null, $secretary->id)))
        ->toThrow(AnnulmentNotAllowed::class, 'Ya tiene notas: la anula un directivo.');

    $this->actingAs($this->admin);
    app(AnnulEnrollmentHandler::class)(new AnnulEnrollmentCommand($graded, 'duplicate', null, $this->admin->id));

    expect(DB::table('enrollments')->where('id', $graded)->value('end_reason'))->toBe('annulled');
});

it('a subject with grades does not leave the curriculum of the active year', function () {
    $this->actingAs($this->admin)->from(tenantUrl($this->institution, '/estructura'))
        ->put(tenantUrl($this->institution, "/estructura/{$this->year}/pensum/media_3"), ['subjects' => [$this->physics]])
        ->assertStatus(409);

    $this->actingAs($this->admin)->from(tenantUrl($this->institution, '/estructura'))
        ->put(tenantUrl($this->institution, "/estructura/{$this->year}/pensum/media_3"), ['subjects' => [$this->math]])
        ->assertSessionHasNoErrors();

    expect(DB::table('curriculum_subjects')->where('grade', 'media_3')->pluck('subject_id')->all())->toBe([$this->math]);
});

it('after a change of grade, the former grades are for reference only and the new grade takes its own', function () {
    $fourth = enrollmentSection($this->year, 'media_4');
    DB::table('curriculum_subjects')->insert(['school_year_id' => $this->year, 'grade' => 'media_4', 'subject_id' => $this->math]);
    DB::table('teaching_assignments')->insert(['section_id' => $fourth, 'subject_id' => $this->math, 'account_id' => $this->teacher->id]);
    DB::table('enrollments')->where('student_id', $this->luis)->update(['ends_on' => '2026-10-20', 'end_reason' => 'grade_change']);
    enrollmentRow($this->luis, $this->year, $fourth, ['grade' => 'media_4', 'starts_on' => '2026-10-20']);
    $newActivity = evaluationActivityRow(evaluationPlanRow($this->year, $fourth, $this->math), ['date' => '2026-10-25']);
    $this->actingAs($this->teacher);

    expect(fn () => app(SaveActivityGradesHandler::class)($this->activity, [$this->luis => '12']))
        ->toThrow(GradeNotValid::class, 'El estudiante no tiene inscripción vigente en la sección.');

    app(SaveActivityGradesHandler::class)($newActivity, [$this->luis => '18']);
    $luis = app(GradebookQuery::class)($this->year, $fourth, $this->math, 1)->rows[0];

    // The 8 of 3.er año stays there, out of the grade of 4.º año.
    expect($luis->termScore)->toBe(18)
        ->and(DB::table('activity_grades')->where('activity_id', $this->activity)->value('score'))->toBe(800);
});
