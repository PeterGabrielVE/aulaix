<?php

namespace Tests\Fixtures\Enrollment;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicStructure\Application\Grades\GradesQuery;
use AulaX\AcademicStructure\Application\Grades\GradeView;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;

/**
 * The sections, curricula and assignments a test sets up.
 */
final class FakeStructureFacts implements StructureFacts
{
    /** @var array<int, list<SectionFact>> School year => sections. */
    public array $sections = [];

    /** @var array<string, list<int>> "year:grade" => subject ids. */
    public array $curricula = [];

    /** @var array<int, list<int>> Account => sections. */
    public array $taught = [];

    /** @var array<int, list<string>> Account => grades. */
    public array $coordinated = [];

    public function section(int $id, int $schoolYearId, string $grade = 'primaria_3', string $letter = 'A', int $capacity = 30): SectionFact
    {
        $view = array_values(array_filter((new GradesQuery)(), fn (GradeView $view) => $view->code === $grade))[0];
        $section = new SectionFact($id, $schoolYearId, $grade, $view->name, $view->level, $letter, $capacity);
        $this->sections[$schoolYearId][] = $section;

        return $section;
    }

    public function sectionsOf(int $schoolYearId): array
    {
        return $this->sections[$schoolYearId] ?? throw SchoolYearNotFound::create();
    }

    public function grades(): array
    {
        return (new GradesQuery)();
    }

    public function curriculum(int $schoolYearId, string $grade): array
    {
        return $this->curricula["{$schoolYearId}:{$grade}"] ?? [];
    }

    public function taughtSections(int $accountId): array
    {
        return $this->taught[$accountId] ?? [];
    }

    public function coordinatedGrades(int $accountId): array
    {
        return $this->coordinated[$accountId] ?? [];
    }
}
