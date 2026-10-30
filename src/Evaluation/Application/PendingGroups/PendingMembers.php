<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\RosterStudentFact;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\OwedPending;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use AulaX\Evaluation\Domain\PendingStanding;
use AulaX\Evaluation\Domain\RevisionRepository;

/**
 * Who takes each pending subject in a school year (RVP-R03.1 – R03.3): the
 * pending subjects from the results of the years before (SPEC-010), of the
 * students enrolled or waiting to graduate, without those approved in the
 * group of another year. One failed is still owed (decision 2). Nothing is
 * kept: correcting a result or withdrawing a student needs no sync.
 */
final readonly class PendingMembers
{
    public function __construct(
        private PendingGroupRepository $groups,
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        private RevisionRepository $revisions,
        private EnrollmentFacts $enrollments,
        private CalendarFacts $calendar,
    ) {}

    /**
     * @return array<string, list<int>> "subject:grade of origin" => the student ids that owe it.
     */
    public function of(int $schoolYearId): array
    {
        $members = [];
        $scores = [];

        foreach ($this->enrollments->owedPendings($schoolYearId) as $owed) {
            if ($this->approvedBefore($owed, $schoolYearId, $scores)) {
                continue;
            }

            $members["{$owed->subjectId}:{$owed->originGrade}"][] = $owed->studentId;
        }

        return array_map(fn (array $students) => array_values(array_unique($students)), $members);
    }

    /**
     * @return list<int>
     */
    public function ofGroup(PendingGroup $group): array
    {
        return $this->of($group->schoolYearId())["{$group->subjectId()}:{$group->originGrade()}"] ?? [];
    }

    public function scoresOf(PendingGroup $group): GroupScores
    {
        return new GroupScores($group, $this->plans, $this->grades, $this->revisions, $this->calendar);
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, RosterStudentFact>
     */
    public function students(array $studentIds): array
    {
        return $this->enrollments->students($studentIds);
    }

    /**
     * @param  array<int, GroupScores>  $scores  Kept between calls, by group id.
     */
    private function approvedBefore(OwedPending $owed, int $schoolYearId, array &$scores): bool
    {
        foreach ($this->groups->groupsOfSubject($owed->subjectId, $owed->originGrade) as $group) {
            if ($group->schoolYearId() === $schoolYearId || $group->schoolYearId() === $owed->originSchoolYearId) {
                continue;
            }

            $scores[(int) $group->id()] ??= $this->scoresOf($group);

            if ($scores[(int) $group->id()]->standing($owed->studentId) === PendingStanding::APPROVED) {
                return true;
            }
        }

        return false;
    }

    public function group(int $schoolYearId, int $subjectId, string $originGrade): ?PendingGroup
    {
        return $this->groups->groupOf($schoolYearId, $subjectId, $originGrade);
    }
}
