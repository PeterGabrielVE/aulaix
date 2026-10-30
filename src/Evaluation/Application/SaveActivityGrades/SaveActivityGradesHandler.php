<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\SaveActivityGrades;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Evaluation\Application\EvaluationAccess;
use AulaX\Evaluation\Application\EvaluationAudit;
use AulaX\Evaluation\Application\EvaluationNotFound;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * Whoever teaches the subject saves the grades of one activity for its
 * section at once (EVA-R02.1): grades from 1 to 20, NP or none, only for
 * the students in the section to whom it applies (EVA-R02.5, R02.8), while
 * the term takes changes (EVA-R06). The grades that changed are recorded,
 * flagged when the term was closed (EVA-R06.3, R10.1).
 */
final readonly class SaveActivityGradesHandler
{
    private EvaluationAccess $access;

    public function __construct(
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        TeachingFacts $teaching,
        CalendarFacts $calendar,
        private EnrollmentFacts $enrollments,
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
        $activity = $plan === null ? null : array_values(array_filter($plan->activities(), fn (Activity $activity) => $activity->id?->value === $activityId))[0] ?? null;

        // The activities of a group of pending subjects are graded by SaveGroupGradesHandler (SPEC-016).
        $sectionId = $plan?->sectionId();

        if ($plan === null || $activity === null || $sectionId === null) {
            throw EvaluationNotFound::create();
        }

        $this->access->ensureTeaches($sectionId, $plan->subjectId());
        $closedTerm = $this->access->ensureEditable($plan->schoolYearId(), $plan->term());

        $marks = [];

        foreach ($grades as $studentId => $grade) {
            $marks[(int) $studentId] = GradeMark::fromInput($grade, "grades.{$studentId}");
        }

        $periods = $this->enrollments->periods($plan->schoolYearId(), array_keys($marks));

        $this->transactions->run(function () use ($activity, $sectionId, $marks, $periods, $closedTerm, $activityId) {
            $activityGrades = $this->grades->forActivity($activity, $sectionId);
            $changes = $activityGrades->record($marks, $periods, $this->clock->now());

            if ($changes === []) {
                return;
            }

            $this->grades->saveGrades($activityGrades);
            $this->audit->record(EvaluationAudit::gradesSaved($activityId, $changes, $closedTerm));
        });
    }
}
