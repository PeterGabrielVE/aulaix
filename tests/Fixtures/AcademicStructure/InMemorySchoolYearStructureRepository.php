<?php

namespace Tests\Fixtures\AcademicStructure;

use AulaX\AcademicStructure\Domain\SchoolYearStructure;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\AcademicStructure\Domain\YearStatus;

/**
 * The structures of one institution's years, kept in memory.
 */
final class InMemorySchoolYearStructureRepository implements SchoolYearStructureRepository
{
    /** @var array<int, SchoolYearStructure> */
    public array $structures = [];

    private int $nextSectionId = 1;

    public function load(int $schoolYearId, YearStatus $status, array $offeredLevels): SchoolYearStructure
    {
        $stored = $this->structures[$schoolYearId] ?? null;

        if ($stored === null) {
            return SchoolYearStructure::empty($schoolYearId, $status, $offeredLevels);
        }

        return SchoolYearStructure::reconstitute(
            $schoolYearId,
            $status,
            $offeredLevels,
            array_map(fn ($section) => clone $section, $stored->sections()),
            $stored->curricula(),
            $stored->coordinators(),
        );
    }

    public function yearOfSection(int $sectionId): ?int
    {
        foreach ($this->structures as $year => $structure) {
            foreach ($structure->sections() as $section) {
                if ($section->id() === $sectionId) {
                    return $year;
                }
            }
        }

        return null;
    }

    public function save(SchoolYearStructure $structure): void
    {
        foreach ($structure->sections() as $section) {
            if ($section->id() === null) {
                $section->identify($this->nextSectionId++);
            }
        }

        $this->structures[$structure->schoolYearId()] = SchoolYearStructure::reconstitute(
            $structure->schoolYearId(),
            $structure->status(),
            [],
            array_map(fn ($section) => clone $section, $structure->sections()),
            $structure->curricula(),
            $structure->coordinators(),
        );
    }
}
