<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\SavePlan;

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
use AulaX\Evaluation\Domain\PlanNotValid;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Whoever teaches a subject of Media General in a section saves its plan for
 * a term (EVA-R01): activities whose weights add up to 100 %, for a subject
 * of the grade's curriculum, while the term takes changes (EVA-R06).
 * Leaving out activities with grades has to be confirmed and takes their
 * grades with them (EVA-R01.5). Recorded (EVA-R10.1).
 */
final readonly class SavePlanHandler
{
    private EvaluationAccess $access;

    public function __construct(
        private EvaluationPlanRepository $plans,
        private ActivityGradesRepository $grades,
        private TeachingFacts $teaching,
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
     * @throws EvaluationNotFound
     */
    public function __invoke(SavePlanCommand $command): void
    {
        $section = $this->teaching->section($command->schoolYearId, $command->sectionId) ?? throw EvaluationNotFound::create();

        if ($section->level !== 'media_general') {
            throw BusinessRuleViolation::onField('section', 'El plan de actividades es de Media General.');
        }

        $this->access->ensureTeaches($section->id, $command->subjectId);

        if (! in_array($command->subjectId, $this->teaching->curriculum($section->schoolYearId, $section->grade), true)) {
            throw PlanNotValid::onField('subject', 'La materia no está en el pensum del grado de la sección.');
        }

        $this->access->ensureEditable($command->schoolYearId, $command->term);
        $dates = $this->calendar->termDates($command->schoolYearId, $command->term) ?? throw EvaluationNotFound::create();

        $this->transactions->run(function () use ($command, $dates) {
            $plan = $this->plans->planOf($command->schoolYearId, $command->sectionId, $command->subjectId, $command->term);
            $before = $plan?->activities() ?? [];

            if ($plan === null) {
                $plan = EvaluationPlan::draft($command->schoolYearId, $command->sectionId, $command->subjectId, $command->term, $command->activities, $dates);
            } else {
                $ids = array_values(array_filter(array_map(fn (Activity $activity) => $activity->id?->value, $before)));
                $plan->replace($command->activities, $dates, $this->grades->withGrades($ids), $command->confirmRemovals);
            }

            $this->plans->save($plan);
            $this->audit->record(EvaluationAudit::planChanged($plan, $before));
        });
    }
}
