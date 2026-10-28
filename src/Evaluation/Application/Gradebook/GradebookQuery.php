<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Gradebook;

use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\RosterStudentFact;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EnrollmentPeriod;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\GradeChange;
use AulaX\Shared\Domain\AuthorizationDenied;
use Collator;

/**
 * The sheet of a subject in a section and a term (EVA-R03): its activities,
 * the grades of the students of the section (NA where an activity does not
 * apply), the grade of the term and the final one. For whoever teaches it,
 * coordinates its grade or manages students (EVA-R07.3 – R07.5); anyone
 * else gets not found (EVA-R07.6).
 */
final readonly class GradebookQuery
{
    private EvaluationAccess $access;

    public function __construct(
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        private TeachingFacts $teaching,
        private CalendarFacts $calendar,
        private EnrollmentFacts $enrollments,
        private EvaluationPermissions $permissions,
    ) {
        $this->access = new EvaluationAccess($permissions, $teaching, $calendar);
    }

    /**
     * @throws AuthorizationDenied
     * @throws EvaluationNotFound
     */
    public function __invoke(int $schoolYearId, int $sectionId, int $subjectId, int $term): GradebookView
    {
        $account = $this->access->ensureCanView();
        $section = $this->teaching->section($schoolYearId, $sectionId) ?? throw EvaluationNotFound::create();
        $teaches = $this->teaching->teaches($account, $sectionId, $subjectId);

        if (! $teaches && ! $this->permissions->seesAll() && ! in_array($section->grade, $this->teaching->coordinatedGrades($account), true)) {
            throw EvaluationNotFound::create();
        }

        if ($section->level !== 'media_general'
            || ! in_array($subjectId, $this->teaching->curriculum($schoolYearId, $section->grade), true)
            || $this->calendar->termStatus($schoolYearId, $term) === null) {
            throw EvaluationNotFound::create();
        }

        $activities = $this->plans->planOf($schoolYearId, $sectionId, $subjectId, $term)?->activities() ?? [];
        $roster = $this->enrollments->roster($schoolYearId, $sectionId);
        $collator = new Collator('es');
        usort($roster, fn (RosterStudentFact $a, RosterStudentFact $b) => $collator->compare($a->fullName, $b->fullName));
        $periods = $this->enrollments->periods($schoolYearId, array_map(fn (RosterStudentFact $student) => $student->studentId, $roster));
        $scores = new StudentScores($this->plans, $this->grades, $schoolYearId, $subjectId, $section->grade);
        $rows = [];

        foreach ($roster as $student) {
            $studentPeriods = $periods[$student->studentId] ?? [];
            $termResult = $scores->term($term, $student->studentId, $studentPeriods);
            $final = $scores->final($student->studentId, $studentPeriods);
            $marks = [];

            foreach ($activities as $activity) {
                $marks[(int) $activity->id?->value] = EnrollmentPeriod::applies($studentPeriods, $sectionId, $activity->date)
                    ? GradeChange::display($scores->gradesOf($activity, $sectionId)->markOf($student->studentId))
                    : 'NA';
            }

            $rows[] = new GradebookRow(
                $student->studentId,
                $student->fullName,
                $student->identification,
                $marks,
                $termResult->state,
                $termResult->score,
                $final->state,
                $final->score,
                $final->failed(),
            );
        }

        return new GradebookView(
            $schoolYearId,
            $section,
            $subjectId,
            $this->teaching->subjectNames($section->level)[$subjectId] ?? '',
            $term,
            $teaches && $this->permissions->canGrade(),
            $this->calendar->gradesEditable($schoolYearId, $term, $this->permissions->canEditClosedTerms()),
            array_map(fn (Activity $activity) => new ActivityView((int) $activity->id?->value, $activity->name, $activity->date->format('Y-m-d'), $activity->weight->display()), $activities),
            $rows,
        );
    }
}
