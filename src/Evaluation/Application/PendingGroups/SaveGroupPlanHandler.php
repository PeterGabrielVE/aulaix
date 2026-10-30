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
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\PendingGroupNotFound;
use AulaX\Evaluation\Domain\PendingGroupRepository;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The teacher assigned to a group of pending subjects saves its plan of
 * activities for a term (RVP-R04.1), with the rules of a plan of SPEC-011
 * (EVA-R01) and of the term (R04.4); recorded like any plan (R07.1).
 */
final readonly class SaveGroupPlanHandler
{
    private EvaluationAccess $access;

    public function __construct(
        private PendingGroupRepository $groups,
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        TeachingFacts $teaching,
        private CalendarFacts $calendar,
        EvaluationPermissions $permissions,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {
        $this->access = new EvaluationAccess($permissions, $teaching, $calendar);
    }

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws PendingGroupNotFound
     */
    public function __invoke(SaveGroupPlanCommand $command): void
    {
        $group = $this->groups->groupById($command->pendingGroupId) ?? throw PendingGroupNotFound::create();
        $this->access->ensureAssigned($group);
        $this->access->ensureEditable($group->schoolYearId(), $command->term);
        $dates = $this->calendar->termDates($group->schoolYearId(), $command->term) ?? throw EvaluationNotFound::create();

        $this->transactions->run(function () use ($command, $group, $dates) {
            $plan = $this->plans->planOfGroup((int) $group->id(), $command->term);
            $before = $plan?->activities() ?? [];

            if ($plan === null) {
                $plan = EvaluationPlan::draft($group->schoolYearId(), PlanOwner::pendingGroup((int) $group->id()), $group->subjectId(), $command->term, $command->activities, $dates);
            } else {
                $ids = array_values(array_filter(array_map(fn (Activity $activity) => $activity->id?->value, $before)));
                $plan->replace($command->activities, $dates, $this->grades->withGrades($ids), $command->confirmRemovals);
            }

            $this->plans->save($plan);
            $this->audit->record(EvaluationAudit::planChanged($plan, $before));
        });
    }
}
