<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Overview;

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\Port\StructureStatistics;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Tenancy\Application\InstitutionSettings\InstitutionSettingsQuery;

/**
 * The public API for other modules (SPEC-013 NAV-R06): the grades a person
 * coordinates in the active year (EAC-R06.3). A whole level counts as every
 * grade of it the institution offers.
 */
final readonly class CoordinatedGradesQuery
{
    public function __construct(
        private StructureStatistics $statistics,
        private SchoolYearLookup $years,
        private InstitutionSettingsQuery $settings,
        private StructurePermissions $permissions,
    ) {}

    /**
     * @return list<string> Grade codes, in order; empty when none.
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(int $accountId): array
    {
        StructureAccess::ensureCanView($this->permissions);
        $year = $this->years->activeYear();

        if ($year === null) {
            return [];
        }

        $coordinated = [];
        $offered = Grade::ofLevels(($this->settings)()->levels);

        foreach ($this->statistics->coordinationsOf($year, $accountId) as $coordination) {
            foreach ($offered as $grade) {
                if ($coordination['grade'] === null ? $grade->level() === $coordination['level'] : $grade->value === $coordination['grade']) {
                    $coordinated[$grade->value] = true;
                }
            }
        }

        return array_values(array_filter(
            array_map(fn (Grade $grade) => $grade->value, $offered),
            fn (string $grade) => isset($coordinated[$grade]),
        ));
    }
}
