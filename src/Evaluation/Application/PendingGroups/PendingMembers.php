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
        $owing = [];

        foreach ($this->enrollments->owedPendings($schoolYearId) as $owed) {
            $owing["{$owed->subjectId}:{$owed->originGrade}"][] = $owed;
        }

        $members = [];

        foreach ($owing as $key => $owedOfSubject) {
            // One read of the groups of the subject, and of each group's revisions, for all who owe it.
            $groups = $this->groups->groupsOfSubject($owedOfSubject[0]->subjectId, $owedOfSubject[0]->originGrade);
            $students = array_map(fn (OwedPending $owed) => $owed->studentId, $owedOfSubject);
            $scores = [];

            foreach ($owedOfSubject as $owed) {
                if (! $this->approvedIn($owed, $groups, $schoolYearId, $scores, $students)) {
                    $members[$key][] = $owed->studentId;
                }
            }
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
     * The groups of a pending subject in the years other than the one it was
     * failed in: where the student takes it.
     *
     * @return list<PendingGroup>
     */
    public function groupsOf(OwedPending $owed): array
    {
        return array_values(array_filter(
            $this->groups->groupsOfSubject($owed->subjectId, $owed->originGrade),
            fn (PendingGroup $group) => $group->schoolYearId() !== $owed->originSchoolYearId,
        ));
    }

    /**
     * Whether the pending subject was approved in the group of any year
     * after it was failed, this one included (RVP-R05.2, R05.5).
     */
    public function isApproved(OwedPending $owed): bool
    {
        $scores = [];

        return $this->approvedIn($owed, $this->groupsOf($owed), null, $scores, [$owed->studentId]);
    }

    /**
     * @param  list<PendingGroup>  $groups  The groups of its subject and grade of origin.
     * @param  ?int  $schoolYearId  The year whose group does not count, if any.
     * @param  array<int, GroupScores>  $scores  Kept between calls, by group id.
     * @param  list<int>  $students  Whose revisions to read at once.
     */
    private function approvedIn(OwedPending $owed, array $groups, ?int $schoolYearId, array &$scores, array $students): bool
    {
        foreach ($groups as $group) {
            if ($group->schoolYearId() === $schoolYearId || $group->schoolYearId() === $owed->originSchoolYearId) {
                continue;
            }

            if (! isset($scores[(int) $group->id()])) {
                $scores[(int) $group->id()] = $this->scoresOf($group);
                $scores[(int) $group->id()]->load($students);
            }

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
