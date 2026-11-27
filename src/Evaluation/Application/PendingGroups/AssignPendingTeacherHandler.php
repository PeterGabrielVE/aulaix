<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Evaluation\Application\EvaluationAudit;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PendingGroupNotFound;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use AulaX\Evaluation\Domain\TeacherNotEligible;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * A manager of the structure assigns the teacher of a group of pending
 * subjects, or leaves it without one (RVP-R03.4, R03.5, Q1): an active
 * member with the role Docente, for a subject and grade of origin someone
 * owes this year. The group is opened with its first teacher; the change is
 * recorded (R07.1).
 */
final readonly class AssignPendingTeacherHandler
{
    public function __construct(
        private PendingMembers $members,
        private PendingGroupRepository $groups,
        private TeachingFacts $teaching,
        private EvaluationPermissions $permissions,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws PendingGroupNotFound
     * @throws TeacherNotEligible
     */
    public function __invoke(AssignPendingTeacherCommand $command): void
    {
        if (! $this->permissions->canAssignPendingTeachers()) {
            throw new AuthorizationDenied('No tienes permiso para gestionar los grupos de pendientes.');
        }

        if (($this->members->of($command->schoolYearId)["{$command->subjectId}:{$command->originGrade}"] ?? []) === []) {
            throw PendingGroupNotFound::create();
        }

        if ($command->accountId !== null && ! isset($this->teaching->activeTeachers()[$command->accountId])) {
            throw TeacherNotEligible::create();
        }

        $this->transactions->run(function () use ($command) {
            $group = $this->groups->groupOf($command->schoolYearId, $command->subjectId, $command->originGrade)
                ?? PendingGroup::open($command->schoolYearId, $command->subjectId, $command->originGrade);
            $change = $group->assignTeacher($command->accountId);

            if ($change === null) {
                return;
            }

            $this->groups->saveGroup($group);
            $this->audit->record(EvaluationAudit::pendingTeacherAssigned((int) $group->id(), $change[0], $change[1]));
        });
    }
}
