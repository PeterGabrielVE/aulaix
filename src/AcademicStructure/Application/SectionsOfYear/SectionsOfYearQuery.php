<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\SectionsOfYear;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\Section;

/**
 * The public API for SPEC-010 (EAC-R08.3): the sections of a school year of
 * the open institution, with their capacity, by grade and letter. Who may
 * see them is the caller's check.
 */
final readonly class SectionsOfYearQuery
{
    public function __construct(private StructureLoader $loader) {}

    /**
     * @return list<SectionSummary>
     *
     * @throws SchoolYearNotFound
     */
    public function __invoke(int $schoolYearId): array
    {
        $sections = $this->loader->forYear($schoolYearId)->sections();
        usort($sections, fn (Section $a, Section $b) => [array_search($a->grade(), Grade::cases(), true), $a->letter()->value]
            <=> [array_search($b->grade(), Grade::cases(), true), $b->letter()->value]);

        return array_map(fn (Section $section) => new SectionSummary(
            (int) $section->id(),
            $section->grade()->value,
            $section->letter()->value,
            $section->capacity()->value,
        ), $sections);
    }
}
