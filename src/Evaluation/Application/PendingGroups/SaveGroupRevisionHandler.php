<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Application\EvaluationAudit;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\GradeChange;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\GradeNotValid;
use AulaX\Evaluation\Domain\NotEditable;
use AulaX\Evaluation\Domain\PendingGroupNotFound;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\RevisionRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The teacher assigned to a group of pending subjects records the revisions
 * of the members who failed it (RVP-R05.1), with the rules of RVP-R01 and
 * R02: the third term closed, the year open, the result of the year not yet
 * recorded. All of a save is checked before any revision changes.
 */
final readonly class SaveGroupRevisionHandler
{
    private EvaluationAccess $access;

    public function __construct(
        private PendingMembers $members,
        private PendingGroupRepository $groups,
        private RevisionRepository $revisions,
        TeachingFacts $teaching,
        private CalendarFacts $calendar,
        private EnrollmentFacts $enrollments,
        EvaluationPermissions $permissions,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {
        $this->access = new EvaluationAccess($permissions, $teaching, $calendar);
    }

    /**
     * @param  array<int, string|int|float|null>  $revisions  Student id => "12,5", "NP" or null for none.
     *
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws PendingGroupNotFound
     */
    public function __invoke(int $pendingGroupId, array $revisions): void
    {
        $group = $this->groups->groupById($pendingGroupId) ?? throw PendingGroupNotFound::create();
        $this->access->ensureAssigned($group);
        $schoolYearId = $group->schoolYearId();
        $thirdTermClosed = $this->calendar->termStatus($schoolYearId, 3) === 'closed';

        if ($thirdTermClosed && ! $this->calendar->gradesEditable($schoolYearId, 3, true)) {
            throw NotEditable::yearClosed();
        }

        $marks = [];

        foreach ($revisions as $studentId => $input) {
            $marks[(int) $studentId] = GradeMark::fromInput($input, "revisions.{$studentId}");
        }

        $studentIds = array_keys($marks);
        $members = $this->members->ofGroup($group);
        $scores = $this->members->scoresOf($group);
        $withResult = $this->enrollments->resultsRecorded($schoolYearId, $studentIds);
        $owner = PlanOwner::pendingGroup((int) $group->id());
        $saved = $this->revisions->revisionsOf($schoolYearId, $group->subjectId(), $owner, $studentIds);
        $changes = [];

        foreach ($marks as $studentId => $mark) {
            if (! in_array($studentId, $members, true)) {
                throw GradeNotValid::notInGroup($studentId);
            }

            $change = $saved[$studentId]->record($mark, $scores->final($studentId), $thirdTermClosed, in_array($studentId, $withResult, true));

            if ($change !== null) {
                $changes[] = $change;
            }
        }

        if ($changes === []) {
            return;
        }

        $this->transactions->run(function () use ($saved, $changes, $owner, $group) {
            foreach (array_map(fn (GradeChange $change) => $change->studentId, $changes) as $studentId) {
                $this->revisions->saveRevision($saved[$studentId]);
            }

            $this->audit->record(EvaluationAudit::revisionsSaved($owner, $group->subjectId(), $changes));
        });
    }
}
