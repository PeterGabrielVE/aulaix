<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

/**
 * Eligible student enrollment inside a section for SPEC-012 batch report cards
 * (DOC-R01.6, R03.1).
 */
final readonly class DocumentSectionEnrollmentView
{
    public function __construct(
        public int $studentId,
        public ?string $outcome,
        public ?string $outcomeLabel,
        public bool $graduated,
    ) {}
}
