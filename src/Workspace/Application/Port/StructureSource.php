<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\Port;

/**
 * The active year's structure, from AcademicStructure.
 */
interface StructureSource
{
    /**
     * The active year's figures, or null when there is no active year; with
     * the current enrollments of its sections (SPEC-010).
     *
     * @param  ?list<string>  $grades  Null: the whole institution.
     * @return ?array{sections: int, capacity: int, gradesWithoutSections: int, sectionsWithoutGuide: int, subjectsWithoutTeacher: int, assignmentsOfInactivePeople: int, enrolled: int}
     */
    public function overview(?array $grades): ?array;

    /**
     * @return list<string> Grade codes; empty when it coordinates none.
     */
    public function coordinatedGrades(int $accountId): array;

    /**
     * @return array{hasActiveYear: bool, teaching: list<array{section: string, subject: string}>, guiding: list<string>}
     */
    public function classesOf(int $accountId): array;
}
