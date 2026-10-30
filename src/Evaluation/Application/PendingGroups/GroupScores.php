<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Domain\Activity;
use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\EvaluationPlan;
use AulaX\Evaluation\Domain\EvaluationPlanRepository;
use AulaX\Evaluation\Domain\FinalAfterRevision;
use AulaX\Evaluation\Domain\FinalScore;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\PendingGroup;
use AulaX\Evaluation\Domain\PendingStanding;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\RevisionRepository;
use AulaX\Evaluation\Domain\ScoredActivity;
use AulaX\Evaluation\Domain\TermResult;
use AulaX\Evaluation\Domain\TermScore;

/**
 * The grades of a group of pending subjects (RVP-R04.3, R05.2): every
 * activity of the group applies to each member (no NA, decision 3), and the
 * final grade after its revision decides how the pending subject stands.
 * Plans, grades and revisions are read once and kept for the instance.
 */
final class GroupScores
{
    /** @var array<int, ?EvaluationPlan> */
    private array $plans = [];

    /** @var array<int, ActivityGrades> */
    private array $grades = [];

    private ?bool $thirdTermClosed = null;

    public function __construct(
        private readonly PendingGroup $group,
        private readonly EvaluationPlanRepository $planRepository,
        private readonly ActivityGradesRepository $gradeRepository,
        private readonly RevisionRepository $revisions,
        private readonly CalendarFacts $calendar,
    ) {}

    /**
     * @return list<Activity>
     */
    public function activitiesOf(int $term): array
    {
        return $this->planOf($term)?->activities() ?? [];
    }

    public function gradesOf(Activity $activity): ActivityGrades
    {
        return $this->grades[(int) $activity->id?->value] ??= $this->gradeRepository->forActivity($activity, $this->owner());
    }

    public function term(int $term, int $studentId): TermResult
    {
        $scored = [];

        foreach ($this->activitiesOf($term) as $activity) {
            $scored[] = new ScoredActivity($activity->weight, true, $this->gradesOf($activity)->markOf($studentId));
        }

        return TermScore::of($scored);
    }

    public function final(int $studentId): FinalScore
    {
        return FinalScore::of(array_map(fn (int $term) => $this->term($term, $studentId), [1, 2, 3]));
    }

    public function revisionOf(int $studentId): ?GradeMark
    {
        return $this->revisions->revisionsOf($this->group->schoolYearId(), $this->group->subjectId(), $this->owner(), [$studentId])[$studentId]->mark();
    }

    public function result(int $studentId): FinalAfterRevision
    {
        return FinalAfterRevision::of($this->final($studentId), $this->revisionOf($studentId));
    }

    public function standing(int $studentId): string
    {
        $this->thirdTermClosed ??= $this->calendar->termStatus($this->group->schoolYearId(), 3) === 'closed';

        return PendingStanding::of($this->result($studentId), $this->thirdTermClosed);
    }

    public function group(): PendingGroup
    {
        return $this->group;
    }

    private function owner(): PlanOwner
    {
        return PlanOwner::pendingGroup((int) $this->group->id());
    }

    private function planOf(int $term): ?EvaluationPlan
    {
        if (! array_key_exists($term, $this->plans)) {
            $this->plans[$term] = $this->group->id() === null ? null : $this->planRepository->planOfGroup($this->group->id(), $term);
        }

        return $this->plans[$term];
    }
}
