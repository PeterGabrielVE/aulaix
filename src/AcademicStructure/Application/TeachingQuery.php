<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application;

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicStructure\Application\Port\AssignmentDirectory;

/**
 * The public API for SPEC-010 and SPEC-011 (EAC-R08.4): whether a person
 * teaches a subject in a section of the open institution's active year,
 * and which sections of that year they teach in or guide. Denies by
 * default: with no active year, nobody teaches anything.
 */
final readonly class TeachingQuery
{
    public function __construct(
        private AssignmentDirectory $assignments,
        private SchoolYearLookup $years,
    ) {}

    public function teaches(int $accountId, int $sectionId, int $subjectId): bool
    {
        $year = $this->years->activeYear();

        return $year !== null && $this->assignments->teaches($accountId, $sectionId, $subjectId, $year);
    }

    /**
     * @return list<int>
     */
    public function sectionsOf(int $accountId): array
    {
        $year = $this->years->activeYear();

        return $year === null ? [] : $this->assignments->sectionIdsOf($accountId, $year);
    }
}
