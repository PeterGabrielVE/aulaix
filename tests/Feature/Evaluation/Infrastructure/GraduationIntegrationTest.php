<?php

use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\NextYear\ExcludedStudent;
use AulaX\Enrollment\Application\NextYear\ProposeNextYearQuery;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-R05.5 with the real modules: whoever waited to graduate
 * from 5.º año graduates once Evaluation says it approved its last pending
 * subject (PendingSubjectsCheck, ADR 0012).
 */
beforeEach(function () {
    foreach (evaluationSetting() as $name => $value) {
        $this->{$name} = $value;
    }

    $this->actingAs(userWithRole($this->institution, 'Director'));

    // Marta was promoted from 5.º año the year before, owing Matemática and Física.
    $this->before = enrollmentYear(2025, 'closed');
    $this->marta = enrollmentStudent('Díaz', 'Marta', '2008-04-20');
    $previous = enrollmentRow($this->marta, $this->before, enrollmentSection($this->before, 'media_5'), [
        'grade' => 'media_5', 'starts_on' => '2025-09-16',
        'outcome' => 'promoted_with_pending', 'outcome_recorded_at' => now(), 'outcome_recorded_by' => 1,
    ]);
    DB::table('enrollment_pending_subjects')->insert([
        ['enrollment_id' => $previous, 'subject_id' => $this->math],
        ['enrollment_id' => $previous, 'subject_id' => $this->physics],
    ]);
});

/**
 * Marta's final grade this year in the group of a pending subject of 5.º año.
 */
function gradePending(int $subjectId, int $score): void
{
    $test = test();
    $group = DB::table('pending_groups')->insertGetId(['school_year_id' => $test->year, 'subject_id' => $subjectId, 'origin_grade' => 'media_5']);

    foreach ([1 => '2026-10-05', 2 => '2027-02-01', 3 => '2027-05-03'] as $term => $date) {
        $plan = DB::table('evaluation_plans')->insertGetId(['school_year_id' => $test->year, 'subject_id' => $subjectId, 'term' => $term, 'pending_group_id' => $group]);
        activityGradeRow(evaluationActivityRow($plan, ['date' => $date]), $test->marta, ['score' => $score * 100]);
    }
}

function martaExcludedBecause(): ?string
{
    $excluded = array_values(array_filter(app(ProposeNextYearQuery::class)(test()->before)->excluded, fn (ExcludedStudent $entry) => $entry->studentId === test()->marta));

    return $excluded[0]->reason ?? null;
}

function enrollMarta(): int
{
    return app(EnrollStudentHandler::class)(new EnrollStudentCommand(test()->marta, test()->year, test()->section, '2027-07-01', null, null, 1))->enrollmentId;
}

it('graduates whoever approved its last pending subject, and not before', function () {
    gradePending($this->math, 15);
    gradePending($this->physics, 8);
    DB::table('terms')->update(['status' => 'closed']);

    // Física failed, its revision still missing: she still waits.
    expect(martaExcludedBecause())->toBe('Está pendiente de egresar.')
        ->and(fn () => enrollMarta())->toThrow(BusinessRuleViolation::class, 'Está pendiente de egresar: solo cursa sus materias pendientes.');

    $group = (int) DB::table('pending_groups')->where('subject_id', $this->physics)->value('id');
    DB::table('grade_revisions')->insert(['school_year_id' => $this->year, 'pending_group_id' => $group, 'subject_id' => $this->physics, 'student_id' => $this->marta, 'score' => 1100, 'not_presented' => false]);

    expect(martaExcludedBecause())->toBe('Egresó.');
});

it('keeps waiting to graduate while the third term is not closed', function () {
    gradePending($this->math, 15);
    gradePending($this->physics, 15);

    expect(martaExcludedBecause())->toBe('Está pendiente de egresar.');
});
