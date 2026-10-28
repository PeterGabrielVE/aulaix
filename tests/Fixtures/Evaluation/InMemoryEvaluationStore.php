<?php

namespace Tests\Fixtures\Evaluation;

use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\StudentMarks;
use AulaX\Evaluation\Domain\StudentMarksRepository;

/**
 * Plans and grades of one institution, kept in memory.
 */
final class InMemoryEvaluationStore implements ActivityGradesRepository, EvaluationPlanRepository, StudentMarksRepository
{
    /** @var array<int, StudentMarks> Student id => marks of school year 2. */
    public array $marks = [];

    /** @var array<string, EvaluationPlan> "year:section:subject:term" => plan. */
    public array $plans = [];

    /** @var array<int, ActivityGrades> Activity id => grades. */
    public array $grades = [];

    private int $nextPlan = 1;

    private int $nextActivity = 1;

    public function planOf(int $schoolYearId, int $sectionId, int $subjectId, int $term): ?EvaluationPlan
    {
        $plan = $this->plans["{$schoolYearId}:{$sectionId}:{$subjectId}:{$term}"] ?? null;

        return $plan === null ? null : clone $plan;
    }

    public function plansOfSubject(int $schoolYearId, int $subjectId, int $term): array
    {
        return array_values(array_map(fn (EvaluationPlan $plan) => clone $plan, array_filter(
            $this->plans,
            fn (EvaluationPlan $plan) => $plan->schoolYearId() === $schoolYearId && $plan->subjectId() === $subjectId && $plan->term() === $term,
        )));
    }

    public function planOfActivity(ActivityId $activityId): ?EvaluationPlan
    {
        foreach ($this->plans as $plan) {
            foreach ($plan->activities() as $activity) {
                if ($activity->id?->value === $activityId->value) {
                    return clone $plan;
                }
            }
        }

        return null;
    }

    public function save(EvaluationPlan $plan): void
    {
        $plan->identify($plan->id() ?? $this->nextPlan++);
        $activities = array_map(
            fn (Activity $activity) => $activity->id !== null ? $activity : new Activity(ActivityId::fromInt($this->nextActivity++), $activity->name, $activity->date, $activity->weight),
            $plan->activities(),
        );
        $kept = array_map(fn (Activity $activity) => $activity->id?->value, $activities);

        foreach (array_keys($this->grades) as $activityId) {
            if ($this->belongsTo($activityId, $plan) && ! in_array($activityId, $kept, true)) {
                unset($this->grades[$activityId]);
            }
        }

        $saved = EvaluationPlan::reconstitute($plan->id(), $plan->schoolYearId(), $plan->sectionId(), $plan->subjectId(), $plan->term(), $activities);
        $this->plans["{$plan->schoolYearId()}:{$plan->sectionId()}:{$plan->subjectId()}:{$plan->term()}"] = $saved;
    }

    public function forActivity(Activity $activity, int $sectionId): ActivityGrades
    {
        $id = $activity->id ?? throw new \LogicException('Only a saved activity has grades.');

        return isset($this->grades[$id->value]) ? clone $this->grades[$id->value] : ActivityGrades::of($id, $sectionId, $activity->date, []);
    }

    public function saveGrades(ActivityGrades $grades): void
    {
        $this->grades[$grades->activityId()->value] = clone $grades;
    }

    public function withGrades(array $activityIds): array
    {
        return array_values(array_filter($activityIds, fn (int $id) => ($this->grades[$id] ?? null)?->marks() !== null && $this->grades[$id]->marks() !== []));
    }

    public function gradedWithin(int $studentId, int $sectionId, \DateTimeImmutable $from, ?\DateTimeImmutable $until): bool
    {
        foreach ($this->plans as $plan) {
            foreach ($plan->sectionId() === $sectionId ? $plan->activities() : [] as $activity) {
                $within = $activity->date >= $from && ($until === null || $activity->date < $until);

                if ($within && ($this->grades[(int) $activity->id?->value] ?? null)?->markOf($studentId) !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    public function subjectsWithGrades(int $schoolYearId, array $sectionIds, array $subjectIds): array
    {
        $subjects = [];

        foreach ($this->plans as $plan) {
            if ($plan->schoolYearId() !== $schoolYearId || ! in_array($plan->sectionId(), $sectionIds, true) || ! in_array($plan->subjectId(), $subjectIds, true)) {
                continue;
            }

            foreach ($plan->activities() as $activity) {
                if (($this->grades[(int) $activity->id?->value] ?? null)?->marks()) {
                    $subjects[] = $plan->subjectId();
                }
            }
        }

        $subjects = array_values(array_unique($subjects));
        sort($subjects);

        return $subjects;
    }

    public function forStudents(int $schoolYearId, array $studentIds): array
    {
        $marks = [];

        foreach ($studentIds as $studentId) {
            $marks[$studentId] = isset($this->marks[$studentId]) ? clone $this->marks[$studentId] : StudentMarks::of($studentId, $schoolYearId, []);
        }

        return $marks;
    }

    public function saveMarks(StudentMarks $marks): void
    {
        $this->marks[$marks->studentId()] = clone $marks;
    }

    /**
     * The saved activities of a plan, in order.
     *
     * @return list<Activity>
     */
    public function activitiesOf(int $sectionId, int $subjectId, int $term, int $schoolYearId = 2): array
    {
        return $this->plans["{$schoolYearId}:{$sectionId}:{$subjectId}:{$term}"]?->activities() ?? [];
    }

    private function belongsTo(int $activityId, EvaluationPlan $plan): bool
    {
        $stored = $this->plans["{$plan->schoolYearId()}:{$plan->sectionId()}:{$plan->subjectId()}:{$plan->term()}"] ?? null;

        foreach ($stored?->activities() ?? [] as $activity) {
            if ($activity->id?->value === $activityId) {
                return true;
            }
        }

        return false;
    }
}
