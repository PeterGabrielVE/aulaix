<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class PendingSubjectView
{
    public function __construct(
        public int $studentId,
        public int $subjectId,
        public string $originGrade,
        public int $originSchoolYearId,
    ) {}
}
