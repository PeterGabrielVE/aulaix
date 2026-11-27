<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PendingGroupRepository;

/**
 * The groups of pending subjects of the active year assigned to the person
 * signed in, for «Notas» (RVP-R04.6).
 */
final readonly class AssignedPendingGroupsQuery
{
    public function __construct(
        private PendingGroupRepository $groups,
        private TeachingFacts $teaching,
        private CalendarFacts $calendar,
        private EvaluationPermissions $permissions,
    ) {}

    /**
     * @return list<array{groupId: int, subject: string, originGrade: string}>
     */
    public function __invoke(): array
    {
        $account = $this->permissions->currentAccountId();
        $year = $this->calendar->activeYear();

        if ($account === null || $year === null) {
            return [];
        }

        $names = $this->teaching->subjectNames('media_general');
        $grades = $this->teaching->gradeNames();

        return array_values(array_map(fn (PendingGroup $group) => [
            'groupId' => (int) $group->id(),
            'subject' => $names[$group->subjectId()] ?? '',
            'originGrade' => $grades[$group->originGrade()] ?? $group->originGrade(),
        ], array_filter($this->groups->groupsOf($year), fn (PendingGroup $group) => $group->teacherAccountId() === $account)));
    }
}
