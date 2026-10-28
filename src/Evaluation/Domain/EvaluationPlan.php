<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The activities of a subject in a section and a term, with weights that
 * add up to exactly 100 % (EVA-R01.1 – R01.3); one activity with all of it
 * is a plan too (EVA-R01.7). Leaving out activities with grades has to be
 * confirmed (EVA-R01.5).
 */
final class EvaluationPlan
{
    /**
     * @param  list<Activity>  $activities
     */
    private function __construct(
        private ?int $id,
        private readonly int $schoolYearId,
        private readonly int $sectionId,
        private readonly int $subjectId,
        private readonly int $term,
        private array $activities,
    ) {}

    /**
     * @param  list<ActivityInput>  $activities
     *
     * @throws PlanNotValid
     */
    public static function draft(int $schoolYearId, int $sectionId, int $subjectId, int $term, array $activities, TermDates $dates): self
    {
        return new self(null, $schoolYearId, $sectionId, $subjectId, $term, self::checked($activities, $dates, []));
    }

    /**
     * @param  list<Activity>  $activities
     */
    public static function reconstitute(?int $id, int $schoolYearId, int $sectionId, int $subjectId, int $term, array $activities): self
    {
        return new self($id, $schoolYearId, $sectionId, $subjectId, $term, $activities);
    }

    /**
     * Replaces the activities: those sent with the id of one of this plan
     * keep it (and their grades); the rest are new.
     *
     * @param  list<ActivityInput>  $activities
     * @param  list<int>  $withGrades  Ids of this plan's activities that have grades.
     * @return list<int> Ids of the activities removed.
     *
     * @throws PlanNotValid
     * @throws RemovalNeedsConfirmation
     */
    public function replace(array $activities, TermDates $dates, array $withGrades, bool $confirmed): array
    {
        $own = [];

        foreach ($this->activities as $activity) {
            if ($activity->id !== null) {
                $own[$activity->id->value] = true;
            }
        }

        $next = self::checked($activities, $dates, $own);
        $kept = array_filter(array_map(fn (Activity $activity) => $activity->id?->value, $next));
        $removed = array_values(array_diff(array_keys($own), $kept));
        $removedWithGrades = array_intersect($removed, $withGrades);

        if ($removedWithGrades !== [] && ! $confirmed) {
            throw RemovalNeedsConfirmation::of(count($removedWithGrades));
        }

        $this->activities = $next;

        return $removed;
    }

    /**
     * @param  list<ActivityInput>  $inputs
     * @param  array<int, true>  $own  Ids an activity may keep.
     * @return list<Activity>
     *
     * @throws PlanNotValid
     */
    private static function checked(array $inputs, TermDates $dates, array $own): array
    {
        $activities = [];
        $sum = 0;

        foreach (array_values($inputs) as $index => $input) {
            $position = $index + 1;
            $name = trim((string) $input->name);

            if ($name === '') {
                throw PlanNotValid::name($position);
            }

            try {
                $weight = Weight::of($input->weight ?? '');
            } catch (InvalidArgumentException) {
                throw PlanNotValid::weight($position);
            }

            $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $input->date);

            if ($date === false || ! $dates->contains($date)) {
                throw PlanNotValid::date($position, $dates);
            }

            $id = $input->id !== null && isset($own[$input->id]) ? ActivityId::fromInt($input->id) : null;
            $activities[] = new Activity($id, $name, $date, $weight);
            $sum += $weight->hundredths;
        }

        if ($sum !== Weight::WHOLE) {
            throw PlanNotValid::sum($sum);
        }

        return $activities;
    }

    public function identify(int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolYearId(): int
    {
        return $this->schoolYearId;
    }

    public function sectionId(): int
    {
        return $this->sectionId;
    }

    public function subjectId(): int
    {
        return $this->subjectId;
    }

    public function term(): int
    {
        return $this->term;
    }

    /**
     * @return list<Activity>
     */
    public function activities(): array
    {
        return $this->activities;
    }
}
