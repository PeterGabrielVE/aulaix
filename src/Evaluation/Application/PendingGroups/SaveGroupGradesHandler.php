<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Application\EvaluationAudit;
use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\GroupAudience;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * The teacher assigned to a group of pending subjects saves the grades of
 * one of its activities (RVP-R04.2): a grade, NP or none, only for its
 * members, to whom every activity applies (decision 3), while the term
 * takes changes (R04.4). The grades that changed are recorded (R07.1).
 */
final readonly class SaveGroupGradesHandler
{
    private EvaluationAccess $access;

    public function __construct(
        private PendingMembers $members,
        private PendingGroupRepository $groups,
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        TeachingFacts $teaching,
        CalendarFacts $calendar,
        EvaluationPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {
        $this->access = new EvaluationAccess($permissions, $teaching, $calendar);
    }

    /**
     * @param  array<int, string|int|float|null>  $grades  Student id => "15,5", "NP" or null for none.
     *
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws EvaluationNotFound
     */
    public function __invoke(int $activityId, array $grades): void
    {
        $plan = $activityId > 0 ? $this->plans->planOfActivity(ActivityId::fromInt($activityId)) : null;
        $groupId = $plan?->owner()->pendingGroupId;
        $activity = $plan === null ? null : array_values(array_filter($plan->activities(), fn (Activity $activity) => $activity->id?->value === $activityId))[0] ?? null;
        $group = $groupId === null ? null : $this->groups->groupById($groupId);

        if ($plan === null || $activity === null || $group === null) {
            throw EvaluationNotFound::create();
        }

        $this->access->ensureAssigned($group);
        $closedTerm = $this->access->ensureEditable($plan->schoolYearId(), $plan->term());
        $marks = [];

        foreach ($grades as $studentId => $grade) {
            $marks[(int) $studentId] = GradeMark::fromInput($grade, "grades.{$studentId}");
        }

        $audience = new GroupAudience($this->members->ofGroup($group));

        $this->transactions->run(function () use ($activity, $plan, $marks, $audience, $closedTerm, $activityId) {
            $activityGrades = $this->grades->forActivity($activity, $plan->owner());
            $changes = $activityGrades->record($marks, $audience, $this->clock->now());

            if ($changes === []) {
                return;
            }

            $this->grades->saveGrades($activityGrades);
            $this->audit->record(EvaluationAudit::gradesSaved($activityId, $changes, $closedTerm));
        });
    }
}
