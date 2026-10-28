<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;

/**
 * The grades of one activity, by student (EVA-R02.1): a grade, NP or none
 * (EVA-R02.3, R02.4). Only for the students in its section today
 * (EVA-R02.5) to whom it applies, never for one that arrived after it
 * (EVA-R02.8). All of a save is checked before any grade changes.
 */
final class ActivityGrades
{
    /**
     * @param  array<int, GradeMark>  $marks  Student id => mark.
     */
    private function __construct(
        private readonly ActivityId $activityId,
        private readonly int $sectionId,
        private readonly DateTimeImmutable $date,
        private array $marks,
    ) {}

    /**
     * @param  array<int, GradeMark>  $marks
     */
    public static function of(ActivityId $activityId, int $sectionId, DateTimeImmutable $date, array $marks): self
    {
        return new self($activityId, $sectionId, $date, $marks);
    }

    /**
     * @param  array<int, ?GradeMark>  $marks  Student id => mark, or null for none.
     * @param  array<int, list<EnrollmentPeriod>>  $periods  Student id => its periods of the year.
     * @return list<GradeChange>
     *
     * @throws GradeNotValid
     */
    public function record(array $marks, array $periods, DateTimeImmutable $today): array
    {
        foreach ($marks as $studentId => $mark) {
            $studentPeriods = $periods[$studentId] ?? [];

            if (! EnrollmentPeriod::currentIn($studentPeriods, $this->sectionId, $today)) {
                throw GradeNotValid::notInSection($studentId);
            }

            if ($mark !== null && ! EnrollmentPeriod::applies($studentPeriods, $this->sectionId, $this->date)) {
                throw GradeNotValid::notApplicable($studentId);
            }
        }

        $changes = [];

        foreach ($marks as $studentId => $mark) {
            $before = GradeChange::display($this->marks[$studentId] ?? null);
            $after = GradeChange::display($mark);

            if ($before !== $after) {
                $changes[] = new GradeChange($studentId, $before, $after);
            }

            if ($mark === null) {
                unset($this->marks[$studentId]);
            } else {
                $this->marks[$studentId] = $mark;
            }
        }

        return $changes;
    }

    public function markOf(int $studentId): ?GradeMark
    {
        return $this->marks[$studentId] ?? null;
    }

    /**
     * @return array<int, GradeMark>
     */
    public function marks(): array
    {
        return $this->marks;
    }

    public function activityId(): ActivityId
    {
        return $this->activityId;
    }

    public function sectionId(): int
    {
        return $this->sectionId;
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }
}
