<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Port;

/**
 * The structure of the open institution, as AcademicStructure tells it
 * (A-06): who teaches, guides and coordinates what, its sections and the
 * curriculum of each grade.
 */
interface TeachingFacts
{
    /**
     * Whether the person teaches the subject in the section of the active year.
     */
    public function teaches(int $accountId, int $sectionId, int $subjectId): bool;

    /**
     * Whether the person guides the section of the active year.
     */
    public function guides(int $accountId, int $sectionId): bool;

    /**
     * @return list<string> Grades of the active year the person coordinates.
     */
    public function coordinatedGrades(int $accountId): array;

    /**
     * Null for a section the year does not have.
     */
    public function section(int $schoolYearId, int $sectionId): ?SectionInfo;

    /**
     * @return list<SectionInfo> The sections of the year, by grade and letter.
     */
    public function sectionsOf(int $schoolYearId): array;

    /**
     * @return list<int> Subject ids of the curriculum of the grade that year.
     */
    public function curriculum(int $schoolYearId, string $grade): array;

    /**
     * @param  string  $level  inicial, primaria or media_general.
     * @return array<int, string> Subject id => name, of the catalog of the level.
     */
    public function subjectNames(string $level): array;

    /**
     * @return list<ClassFact> The subjects the person teaches in the active year, by section.
     */
    public function taughtClasses(int $accountId): array;

    /**
     * @return list<ClassFact> Every subject of the curriculum of every section of the year, by section.
     */
    public function classesOf(int $schoolYearId): array;

    /**
     * Who teaches each subject of each section of the year, and who guides
     * each section, by name.
     *
     * @return array{teachers: array<int, array<int, string>>, guides: array<int, string>}
     */
    public function staffNames(int $schoolYearId): array;

    /**
     * @return array<int, string> The active members with the role Docente, account id => name (RVP-R03.5).
     */
    public function activeTeachers(): array;

    /**
     * @return array<string, string> Grade code => name ("media_2" => "2.º año").
     */
    public function gradeNames(): array;
}
