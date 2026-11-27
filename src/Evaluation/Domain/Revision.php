<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The revision of a student's failed final grade in a subject and year, in
 * a section or in a group of pending subjects (RVP-R01): a grade from 1 to
 * 20, NP or none. Only for a failed final (R01.4), with the third term
 * closed (R01.5), and while the result of the year is not recorded (R01.7).
 */
final class Revision
{
    private function __construct(
        private ?int $id,
        private readonly int $schoolYearId,
        private readonly int $subjectId,
        private readonly int $studentId,
        private readonly PlanOwner $owner,
        private ?GradeMark $mark,
    ) {}

    public static function of(?int $id, int $schoolYearId, int $subjectId, int $studentId, PlanOwner $owner, ?GradeMark $mark): self
    {
        return new self($id, $schoolYearId, $subjectId, $studentId, $owner, $mark);
    }

    /**
     * @return ?GradeChange What changed, or null when nothing did.
     *
     * @throws RevisionNotAllowed
     */
    public function record(?GradeMark $mark, FinalScore $final, bool $thirdTermClosed, bool $resultRecorded): ?GradeChange
    {
        if (! $thirdTermClosed) {
            throw RevisionNotAllowed::tooEarly();
        }

        if (! $final->isComplete() || ! $final->failed()) {
            throw RevisionNotAllowed::notFailed($this->studentId);
        }

        $before = GradeChange::display($this->mark);
        $after = GradeChange::display($mark);

        if ($before === $after) {
            return null;
        }

        if ($resultRecorded) {
            throw RevisionNotAllowed::resultRecorded($this->studentId);
        }

        $this->mark = $mark;

        return new GradeChange($this->studentId, $before, $after);
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

    public function subjectId(): int
    {
        return $this->subjectId;
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function owner(): PlanOwner
    {
        return $this->owner;
    }

    public function mark(): ?GradeMark
    {
        return $this->mark;
    }
}
