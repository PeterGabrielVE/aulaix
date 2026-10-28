<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The plans of evaluation of the open institution (Row Level Security,
 * S-01).
 */
interface EvaluationPlanRepository
{
    public function planOf(int $schoolYearId, int $sectionId, int $subjectId, int $term): ?EvaluationPlan;

    /**
     * The plans of a subject in a term, of every section: a student who
     * changed section counts the activities of both (EVA-R02.7).
     *
     * @return list<EvaluationPlan>
     */
    public function plansOfSubject(int $schoolYearId, int $subjectId, int $term): array;

    /**
     * The plan an activity belongs to.
     */
    public function planOfActivity(ActivityId $activityId): ?EvaluationPlan;

    /**
     * Saves the activities, giving the new ones their id; the ones left out
     * go with their grades.
     */
    public function save(EvaluationPlan $plan): void;
}
