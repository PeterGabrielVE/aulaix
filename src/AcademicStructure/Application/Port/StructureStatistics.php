<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Port;

/**
 * Read side of the structure in figures (A-08): aggregate queries, without
 * loading the year's aggregate. Null grades means every grade.
 */
interface StructureStatistics
{
    /**
     * @param  ?list<string>  $grades
     * @return array{sections: int, capacity: int, withoutGuide: int, withoutTeacher: int, gradesWithSections: list<string>}
     */
    public function summary(int $schoolYearId, ?array $grades): array;

    /**
     * The account of every teaching and guide post, once per post.
     *
     * @param  ?list<string>  $grades
     * @return list<int>
     */
    public function assignedAccounts(int $schoolYearId, ?array $grades): array;

    /**
     * @return list<array{level: string, grade: ?string}> Null grade: the whole level.
     */
    public function coordinationsOf(int $schoolYearId, int $accountId): array;
}
