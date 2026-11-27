<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

/**
 * Non-annulled enrollment projection for SPEC-012 Documents (A-06).
 */
final readonly class DocumentEnrollmentView
{
    public function __construct(
        public int $enrollmentId,
        public int $schoolYearId,
        public int $sectionId,
        public string $gradeCode,
        public string $gradeName,
        public string $level,
        public string $sectionLetter,
        public string $startsOn,
        public ?string $endsOn,
        public ?string $endReason,
        public bool $isCurrent,
        public ?string $outcome,
        public ?string $outcomeLabel,
        public bool $waitsToGraduate,
        public bool $graduated,
    ) {}
}
