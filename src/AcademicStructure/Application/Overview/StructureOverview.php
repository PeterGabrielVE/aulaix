<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Overview;

/**
 * The active year's structure in figures (SPEC-013 NAV-R05.1, R05.3, R06):
 * for the whole institution or for some grades.
 */
final readonly class StructureOverview
{
    /**
     * @param  list<string>  $gradesWithoutSections  Grade codes the institution offers with no section yet, in order.
     * @param  int  $subjectsWithoutTeacher  Section and curriculum subject pairs nobody teaches.
     * @param  int  $assignmentsOfInactivePeople  Teaching and guide posts held by people no longer active (EAC-R08.5).
     */
    public function __construct(
        public int $schoolYearId,
        public int $sections,
        public int $capacity,
        public array $gradesWithoutSections,
        public int $sectionsWithoutGuide,
        public int $subjectsWithoutTeacher,
        public int $assignmentsOfInactivePeople,
    ) {}
}
