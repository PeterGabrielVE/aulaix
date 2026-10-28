<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure;

use AulaX\AcademicStructure\Application\Grades\CurriculumQuery;
use AulaX\AcademicStructure\Application\Grades\GradesQuery;
use AulaX\AcademicStructure\Application\Grades\GradeView;
use AulaX\AcademicStructure\Application\Overview\CoordinatedGradesQuery;
use AulaX\AcademicStructure\Application\SectionsOfYear\SectionsOfYearQuery;
use AulaX\AcademicStructure\Application\SectionsOfYear\SectionSummary;
use AulaX\AcademicStructure\Application\TeachingQuery;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;

/**
 * The academic structure, through AcademicStructure's public queries (A-06).
 */
final readonly class AcademicStructureFacts implements StructureFacts
{
    public function __construct(
        private SectionsOfYearQuery $sections,
        private GradesQuery $grades,
        private CurriculumQuery $curriculum,
        private TeachingQuery $teaching,
        private CoordinatedGradesQuery $coordinated,
    ) {}

    public function sectionsOf(int $schoolYearId): array
    {
        $grades = array_column(array_map(fn (GradeView $grade) => [$grade->code, $grade], ($this->grades)()), 1, 0);

        return array_map(fn (SectionSummary $section) => new SectionFact(
            $section->id,
            $schoolYearId,
            $section->grade,
            $grades[$section->grade]->name,
            $grades[$section->grade]->level,
            $section->letter,
            $section->capacity,
        ), ($this->sections)($schoolYearId));
    }

    public function grades(): array
    {
        return ($this->grades)();
    }

    public function curriculum(int $schoolYearId, string $grade): array
    {
        return ($this->curriculum)($schoolYearId, $grade);
    }

    public function taughtSections(int $accountId): array
    {
        return $this->teaching->sectionsOf($accountId);
    }

    public function coordinatedGrades(int $accountId): array
    {
        return ($this->coordinated)($accountId);
    }
}
