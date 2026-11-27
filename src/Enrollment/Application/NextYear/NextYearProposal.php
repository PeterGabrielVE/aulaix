<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\NextYear;

use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Roster\SectionOverview;

final readonly class NextYearProposal
{
    /**
     * @param  list<ProposalEntry>  $entries  By name.
     * @param  list<ExcludedStudent>  $excluded  By name, each with why (MAT-R07.2, R07.3, R07.7, R15.3).
     * @param  list<SectionOverview>  $sections  Of the next year, with their current enrollments.
     * @param  array<int, SectionFact>  $sectionFacts  By id, for the enrollment itself.
     */
    public function __construct(
        public int $fromSchoolYearId,
        public int $toSchoolYearId,
        public array $entries,
        public array $excluded,
        public array $sections,
        public array $sectionFacts,
    ) {}

    public function entryOf(int $studentId): ?ProposalEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->studentId === $studentId) {
                return $entry;
            }
        }

        return null;
    }
}
