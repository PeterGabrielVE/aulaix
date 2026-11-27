<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Grades;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The public API for SPEC-010: the subjects of the curriculum of a grade in
 * a school year of the open institution, for the pending subjects of a
 * promotion. Who may see them is the caller's check.
 */
final readonly class CurriculumQuery
{
    public function __construct(private StructureLoader $loader) {}

    /**
     * @return list<int> Subject ids.
     *
     * @throws SchoolYearNotFound
     * @throws BusinessRuleViolation For an unknown grade.
     */
    public function __invoke(int $schoolYearId, string $grade): array
    {
        return $this->loader->forYear($schoolYearId)->curriculumOf(Grade::fromInput($grade));
    }
}
