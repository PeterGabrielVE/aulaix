<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Overview;

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicStructure\Application\Port\StaffDirectory;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\Port\StructureStatistics;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Tenancy\Application\InstitutionSettings\InstitutionSettingsQuery;

/**
 * The public API for other modules (SPEC-013 NAV-R05, R06): the active
 * year's structure in figures, for the whole institution or for the grades
 * given. Needs ver-estructura, as the structure screen (S-04).
 */
final readonly class StructureOverviewQuery
{
    public function __construct(
        private StructureStatistics $statistics,
        private SchoolYearLookup $years,
        private InstitutionSettingsQuery $settings,
        private StaffDirectory $staff,
        private StructurePermissions $permissions,
    ) {}

    /**
     * @param  ?list<string>  $grades  Null: every grade.
     * @return ?StructureOverview Null when there is no active year.
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(?array $grades = null): ?StructureOverview
    {
        StructureAccess::ensureCanView($this->permissions);
        $year = $this->years->activeYear();

        if ($year === null) {
            return null;
        }

        $summary = $this->statistics->summary($year, $grades);
        $offered = array_map(fn (Grade $grade) => $grade->value, Grade::ofLevels(($this->settings)()->levels));

        if ($grades !== null) {
            $offered = array_values(array_intersect($offered, $grades));
        }

        return new StructureOverview(
            $year,
            $summary['sections'],
            $summary['capacity'],
            array_values(array_diff($offered, $summary['gradesWithSections'])),
            $summary['withoutGuide'],
            $summary['withoutTeacher'],
            $this->postsOfInactivePeople($year, $grades),
        );
    }

    /**
     * @param  ?list<string>  $grades
     */
    private function postsOfInactivePeople(int $year, ?array $grades): int
    {
        $accounts = $this->statistics->assignedAccounts($year, $grades);
        $people = $this->staff->describe(array_values(array_unique($accounts)));

        return count(array_filter($accounts, fn (int $account) => ! ($people[$account]['active'] ?? false)));
    }
}
