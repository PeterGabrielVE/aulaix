<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicStructure\Application\Grades\GradeView;

/**
 * The academic structure of the open institution, as AcademicStructure
 * tells it (A-06).
 */
interface StructureFacts
{
    /**
     * @return list<SectionFact> By grade and letter.
     *
     * @throws SchoolYearNotFound
     */
    public function sectionsOf(int $schoolYearId): array;

    /**
     * @return list<GradeView> In their official order.
     */
    public function grades(): array;

    /**
     * @return list<int> Subject ids of the curriculum of the grade that year.
     */
    public function curriculum(int $schoolYearId, string $grade): array;

    /**
     * @return list<int> Sections of the active year the person teaches in or guides.
     */
    public function taughtSections(int $accountId): array;

    /**
     * @return list<string> Grades of the active year the person coordinates.
     */
    public function coordinatedGrades(int $accountId): array;
}
