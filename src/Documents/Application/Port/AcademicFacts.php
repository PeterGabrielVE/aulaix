<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\Port;

/**
 * School years, terms, sections and Coordinator grade scope from
 * AcademicCalendar and AcademicStructure (A-06).
 */
interface AcademicFacts
{
    public function activeSchoolYearId(): ?int;

    public function schoolYearName(int $schoolYearId): ?string;

    /**
     * @return list<array{id: int, name: string, status: string}>
     */
    public function schoolYears(): array;

    /**
     * Status of a term ('pending', 'open', 'closed'), or null when missing.
     */
    public function termStatus(int $schoolYearId, int $term): ?string;

    /**
     * End date (Y-m-d) of the given term, or null when missing.
     */
    public function termEndDate(int $schoolYearId, int $term): ?string;

    /**
     * @return ?array{id: int, schoolYearId: int, gradeCode: string, gradeName: string, level: string, letter: string}
     */
    public function section(int $schoolYearId, int $sectionId): ?array;

    /**
     * @return list<string> Grade codes coordinated by the account in the active year.
     */
    public function coordinatedGrades(int $accountId): array;
}
