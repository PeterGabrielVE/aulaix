<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\NextYear;

final readonly class ProposalEntry
{
    /**
     * @param  list<int>  $sectionIds  The sections of $toGrade in the next year.
     */
    public function __construct(
        public int $studentId,
        public string $fullName,
        public string $outcome,
        public string $toGrade,
        public string $toGradeName,
        public array $sectionIds,
    ) {}
}
