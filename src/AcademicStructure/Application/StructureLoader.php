<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application;

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicStructure\Domain\SchoolYearStructure;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\AcademicStructure\Domain\SectionNotFound;
use AulaX\AcademicStructure\Domain\YearStatus;
use AulaX\Tenancy\Application\InstitutionSettings\InstitutionSettingsQuery;

/**
 * Loads a year's structure with what other modules say about it: the state
 * of the year (SPEC-007) and the levels of the institution (SPEC-006).
 */
final readonly class StructureLoader
{
    public function __construct(
        private SchoolYearStructureRepository $structures,
        private SchoolYearLookup $years,
        private InstitutionSettingsQuery $settings,
    ) {}

    /**
     * @throws SchoolYearNotFound
     */
    public function forYear(int $schoolYearId): SchoolYearStructure
    {
        $status = $this->years->statusOf($schoolYearId) ?? throw SchoolYearNotFound::create();

        return $this->structures->load($schoolYearId, YearStatus::from($status), ($this->settings)()->levels);
    }

    /**
     * @throws SectionNotFound
     */
    public function forSection(int $sectionId): SchoolYearStructure
    {
        $year = $sectionId > 0 ? $this->structures->yearOfSection($sectionId) : null;

        return $this->forYear($year ?? throw SectionNotFound::create());
    }
}
