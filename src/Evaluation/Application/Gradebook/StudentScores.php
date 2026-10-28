<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Gradebook;

use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EnrollmentPeriod;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\FinalScore;
use AulaX\Evaluation\Domain\ScoredActivity;
use AulaX\Evaluation\Domain\TermResult;
use AulaX\Evaluation\Domain\TermScore;

/**
 * The term and final grades of students in a subject, from the plans of
 * every section of the subject: an activity counts for a student only if
 * it was in the activity's section on its date (EVA-R02.7, R02.8). Plans
 * and grades are read once and kept for the instance.
 */
final class StudentScores
{
    /** @var array<int, list<EvaluationPlan>> */
    private array $plans = [];

    /** @var array<int, ActivityGrades> */
    private array $grades = [];

    public function __construct(
        private readonly EvaluationPlanRepository $planRepository,
        private readonly ActivityGradesRepository $gradeRepository,
        private readonly int $schoolYearId,
        private readonly int $subjectId,
    ) {}

    /**
     * @param  list<EnrollmentPeriod>  $periods
     */
    public function term(int $term, int $studentId, array $periods): TermResult
    {
        $scored = [];

        foreach ($this->plansOf($term) as $plan) {
            foreach ($plan->activities() as $activity) {
                $scored[] = new ScoredActivity(
                    $activity->weight,
                    EnrollmentPeriod::applies($periods, $plan->sectionId(), $activity->date),
                    $this->gradesOf($activity, $plan->sectionId())->markOf($studentId),
                );
            }
        }

        return TermScore::of($scored);
    }

    /**
     * @param  list<EnrollmentPeriod>  $periods
     */
    public function final(int $studentId, array $periods): FinalScore
    {
        return FinalScore::of(array_map(fn (int $term) => $this->term($term, $studentId, $periods), [1, 2, 3]));
    }

    public function gradesOf(Activity $activity, int $sectionId): ActivityGrades
    {
        $id = (int) $activity->id?->value;

        return $this->grades[$id] ??= $this->gradeRepository->forActivity($activity, $sectionId);
    }

    /**
     * @return list<EvaluationPlan>
     */
    private function plansOf(int $term): array
    {
        return $this->plans[$term] ??= $this->planRepository->plansOfSubject($this->schoolYearId, $this->subjectId, $term);
    }
}
