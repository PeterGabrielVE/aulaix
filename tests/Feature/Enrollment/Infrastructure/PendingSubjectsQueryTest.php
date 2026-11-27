<?php

use AulaX\Enrollment\Application\Roster\PendingSubjectsQuery;
use AulaX\Enrollment\Application\Roster\PendingSubjectView;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/Evaluation/evaluation_helpers.php';

/**
 * SPEC-016 RVP-R03.1 – R03.3: the pending subjects from the results of the
 * years before, of whoever is enrolled this year or waits to graduate; not
 * of whoever left.
 */
beforeEach(function () {
    tenant();
    $this->before = enrollmentYear(2025, 'closed');
    $this->year = enrollmentYear(2026);
    $this->math = evaluationSubject('Matemática');
    $this->physics = evaluationSubject('Física');
});

function resultWithPending(int $studentId, int $schoolYearId, string $grade, array $subjects): void
{
    $section = enrollmentSection($schoolYearId, $grade, chr(65 + random_int(0, 20)));
    $enrollment = enrollmentRow($studentId, $schoolYearId, $section, [
        'grade' => $grade,
        'starts_on' => $schoolYearId === test()->before ? '2025-09-16' : '2026-09-16',
        'ends_on' => $schoolYearId === test()->before ? '2026-07-15' : null,
        'end_reason' => $schoolYearId === test()->before ? 'section_change' : null,
        'outcome' => 'promoted_with_pending',
        'outcome_recorded_at' => now(),
        'outcome_recorded_by' => 1,
    ]);

    foreach ($subjects as $subject) {
        DB::table('enrollment_pending_subjects')->insert(['enrollment_id' => $enrollment, 'subject_id' => $subject]);
    }
}

it('gives the pending subjects of the students enrolled this year and of those waiting to graduate', function () {
    $ana = enrollmentStudent('Pérez', 'Ana', '2011-03-10');
    resultWithPending($ana, $this->before, 'media_3', [$this->math]);
    enrollmentRow($ana, $this->year, enrollmentSection($this->year, 'media_4'), ['grade' => 'media_4']);

    $luis = enrollmentStudent('Álvarez', 'Luis', '2008-05-02');
    resultWithPending($luis, $this->before, 'media_5', [$this->physics]);

    $eva = enrollmentStudent('Mora', 'Eva', '2011-07-01', 'withdrawn');
    resultWithPending($eva, $this->before, 'media_3', [$this->math]);

    $views = app(PendingSubjectsQuery::class)($this->year);

    expect(array_map(fn (PendingSubjectView $view) => [$view->studentId, $view->subjectId, $view->originGrade, $view->originSchoolYearId], $views))
        ->toEqualCanonicalizing([[$ana, $this->math, 'media_3', $this->before], [$luis, $this->physics, 'media_5', $this->before]]);
});

it('leaves out the results of the year itself', function () {
    $ana = enrollmentStudent('Pérez', 'Ana', '2011-03-10');
    resultWithPending($ana, $this->year, 'media_3', [$this->math]);

    expect(app(PendingSubjectsQuery::class)($this->year))->toBe([]);
});
