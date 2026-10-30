<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Application\Gradebook\ActivityView;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\RosterStudentFact;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\GradeChange;
use AulaX\Evaluation\Domain\PendingGroupNotFound;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use AulaX\Shared\Domain\AuthorizationDenied;
use Collator;

/**
 * The sheet of a group of pending subjects in a term (RVP-R04.3, R04.6,
 * R05.1, R05.2): its activities, the grades of its members, the grade of
 * the term, the final one, the revision and how each stands. For the
 * teacher assigned, a coordinator of the grade of origin or whoever manages
 * students; anyone else gets not found.
 */
final readonly class PendingGroupSheetQuery
{
    private EvaluationAccess $access;

    public function __construct(
        private PendingMembers $members,
        private PendingGroupRepository $groups,
        private TeachingFacts $teaching,
        private CalendarFacts $calendar,
        private EvaluationPermissions $permissions,
    ) {
        $this->access = new EvaluationAccess($permissions, $teaching, $calendar);
    }

    /**
     * @throws AuthorizationDenied
     * @throws PendingGroupNotFound
     */
    public function __invoke(int $pendingGroupId, int $term): PendingGroupSheetView
    {
        $account = $this->access->ensureCanView();
        $group = $this->groups->groupById($pendingGroupId) ?? throw PendingGroupNotFound::create();
        $assigned = $group->teacherAccountId() === $account;

        if (! $assigned && ! $this->permissions->seesAll() && ! in_array($group->originGrade(), $this->teaching->coordinatedGrades($account), true)) {
            throw PendingGroupNotFound::create();
        }

        $schoolYearId = $group->schoolYearId();

        if ($this->calendar->termStatus($schoolYearId, $term) === null) {
            throw PendingGroupNotFound::create();
        }

        $scores = $this->members->scoresOf($group);
        $activities = $scores->activitiesOf($term);
        $students = $this->members->students($this->members->ofGroup($group));
        $collator = new Collator('es');
        usort($students, fn (RosterStudentFact $a, RosterStudentFact $b) => $collator->compare($a->fullName, $b->fullName));
        $canGrade = $assigned && $this->permissions->canGrade();
        $rows = [];

        foreach ($students as $student) {
            $termResult = $scores->term($term, $student->studentId);
            $final = $scores->final($student->studentId);
            $decided = $scores->result($student->studentId);
            $marks = [];

            foreach ($activities as $activity) {
                $marks[(int) $activity->id?->value] = GradeChange::display($scores->gradesOf($activity)->markOf($student->studentId));
            }

            $rows[] = new PendingGroupRow(
                $student->studentId,
                $student->fullName,
                $student->identification,
                $marks,
                $termResult->state,
                $termResult->score,
                $final->state,
                $final->score,
                $final->failed(),
                GradeChange::display($scores->revisionOf($student->studentId)),
                $decided,
                $scores->standing($student->studentId),
            );
        }

        return new PendingGroupSheetView(
            (int) $group->id(),
            $schoolYearId,
            $group->subjectId(),
            $this->teaching->subjectNames('media_general')[$group->subjectId()] ?? '',
            $group->originGrade(),
            $term,
            $canGrade,
            $this->calendar->gradesEditable($schoolYearId, $term, $this->permissions->canEditClosedTerms()),
            // RVP-R05.1, R01.5, R01.6: with the third term closed, in an open year.
            $canGrade && $this->calendar->termStatus($schoolYearId, 3) === 'closed' && $this->calendar->gradesEditable($schoolYearId, 3, true),
            array_map(fn (Activity $activity) => new ActivityView((int) $activity->id?->value, $activity->name, $activity->date->format('Y-m-d'), $activity->weight->display()), $activities),
            $rows,
            $this->teaching->gradeNames()[$group->originGrade()] ?? $group->originGrade(),
        );
    }
}
