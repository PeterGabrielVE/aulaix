<?php

use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-011 EVA-R08.1, R08.4, R08.5 and SPEC-016 RVP-R02.3, R02.4 with the
 * real modules: «Prosecución» shows the result proposed from the final
 * grades after their revision, and the manager confirms it as any other
 * result (MAT-R06).
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->eva = enrollmentStudent('Mora', 'Eva', '2011-07-01');
    enrollmentRow($this->eva, $this->year, $this->section, ['grade' => 'media_3']);
    $this->director = userWithRole($this->institution, 'Director');
    $dates = [1 => '2026-10-05', 2 => '2027-02-01', 3 => '2027-05-03'];

    // Ana passes both; Luis fails both; Eva has no grade in Física.
    foreach ([$this->math => [15, 8, 12], $this->physics => [12, 7, null]] as $subject => [$ana, $luis, $eva]) {
        foreach ($dates as $term => $date) {
            $activity = evaluationActivityRow(evaluationPlanRow($this->year, $this->section, $subject, $term), ['date' => $date]);
            activityGradeRow($activity, $this->ana, ['score' => $ana * 100]);
            activityGradeRow($activity, $this->luis, ['score' => $luis * 100]);

            if ($eva !== null) {
                activityGradeRow($activity, $this->eva, ['score' => $eva * 100]);
            }
        }
    }

    DB::table('terms')->update(['status' => 'closed']);

    // Luis did not present the revision of either (SPEC-016).
    foreach ([$this->math, $this->physics] as $subject) {
        revisionRow($this->year, $this->section, $subject, $this->luis);
    }
});

function promotionUrl(string $path): string
{
    return tenantUrl(test()->institution, $path);
}

it('shows the result proposed from the final grades, and what is missing to propose it', function () {
    $this->actingAs($this->director)->get(promotionUrl("/prosecucion/{$this->year}/secciones/{$this->section}"))->assertOk()->assertInertia(fn ($page) => $page
        ->component('Enrollment/Promotion')
        ->where('sheet.students.0.fullName', 'Álvarez, Luis')
        ->where('sheet.students.0.proposal', ['outcome' => 'promoted_with_pending', 'pendingSubjects' => [$this->math, $this->physics], 'missing' => []])
        ->where('sheet.students.1.fullName', 'Mora, Eva')
        ->where('sheet.students.1.proposal', ['outcome' => null, 'pendingSubjects' => [], 'missing' => ['Falta la definitiva de Física.']])
        ->where('sheet.students.2.proposal.outcome', 'promoted'));
});

it('confirms the proposal as the result of the year', function () {
    $enrollment = (int) DB::table('enrollments')->where('student_id', $this->luis)->value('id');

    $this->actingAs($this->director)
        ->put(promotionUrl("/inscripciones/{$enrollment}/resultado"), ['outcome' => 'promoted_with_pending', 'pending_subjects' => [$this->math, $this->physics]])
        ->assertSessionHasNoErrors();

    expect(DB::table('enrollments')->where('id', $enrollment)->value('outcome'))->toBe('promoted_with_pending')
        ->and(DB::table('enrollment_pending_subjects')->where('enrollment_id', $enrollment)->pluck('subject_id')->sort()->values()->all())
        ->toBe(collect([$this->math, $this->physics])->sort()->values()->all());
});

it('counts the revisions, and waits for the one still missing', function () {
    DB::table('grade_revisions')->where('subject_id', $this->physics)->update(['score' => 1200, 'not_presented' => false]);

    $this->actingAs($this->director)->get(promotionUrl("/prosecucion/{$this->year}/secciones/{$this->section}"))->assertInertia(fn ($page) => $page
        ->where('sheet.students.0.proposal', ['outcome' => 'promoted_with_pending', 'pendingSubjects' => [$this->math], 'missing' => []]));

    DB::table('grade_revisions')->where('subject_id', $this->math)->delete();

    $this->actingAs($this->director)->get(promotionUrl("/prosecucion/{$this->year}/secciones/{$this->section}"))->assertInertia(fn ($page) => $page
        ->where('sheet.students.0.proposal', ['outcome' => null, 'pendingSubjects' => [], 'missing' => ['Falta la revisión de Matemática.']]));
});
