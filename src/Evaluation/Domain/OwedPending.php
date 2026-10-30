<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * A pending subject of a student, from the result of a year (SPEC-010): the
 * subject, the grade where it was failed and that year.
 */
final readonly class OwedPending
{
    public function __construct(
        public int $studentId,
        public int $subjectId,
        public string $originGrade,
        public int $originSchoolYearId,
    ) {}

    public function belongsTo(PendingGroup $group): bool
    {
        return $group->subjectId() === $this->subjectId && $group->originGrade() === $this->originGrade;
    }
}
