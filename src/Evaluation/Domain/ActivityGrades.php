<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;

/**
 * The grades of one activity, by student (EVA-R02.1): a grade, NP or none
 * (EVA-R02.3, R02.4). Only for the students in its section or group today
 * (EVA-R02.5) to whom it applies, never for one that arrived after it
 * (EVA-R02.8); who they are depends on the owner of its plan (ADR 0013).
 * All of a save is checked before any grade changes.
 */
final class ActivityGrades
{
    /**
     * @param  array<int, GradeMark>  $marks  Student id => mark.
     */
    private function __construct(
        private readonly ActivityId $activityId,
        private readonly PlanOwner $owner,
        private readonly DateTimeImmutable $date,
        private array $marks,
    ) {}

    /**
     * @param  int|PlanOwner  $owner  A section by its id, or any owner.
     * @param  array<int, GradeMark>  $marks
     */
    public static function of(ActivityId $activityId, int|PlanOwner $owner, DateTimeImmutable $date, array $marks): self
    {
        return new self($activityId, $owner instanceof PlanOwner ? $owner : PlanOwner::section($owner), $date, $marks);
    }

    /**
     * @param  array<int, ?GradeMark>  $marks  Student id => mark, or null for none.
     * @param  array<int, list<EnrollmentPeriod>>|ActivityAudience  $audience  The periods of the students of its section (student id => periods of the year), or any audience.
     * @return list<GradeChange>
     *
     * @throws GradeNotValid
     */
    public function record(array $marks, array|ActivityAudience $audience, DateTimeImmutable $today): array
    {
        if (is_array($audience)) {
            $audience = new SectionAudience($audience, (int) $this->owner->sectionId);
        }

        foreach ($marks as $studentId => $mark) {
            if (! $audience->isCurrent($studentId, $today)) {
                throw $audience->notCurrent($studentId);
            }

            if ($mark !== null && ! $audience->applies($studentId, $this->date)) {
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

    public function owner(): PlanOwner
    {
        return $this->owner;
    }

    /**
     * Null for an activity of a group of pending subjects.
     */
    public function sectionId(): ?int
    {
        return $this->owner->sectionId;
    }

    public function date(): DateTimeImmutable
    {
        return $this->date;
    }
}
