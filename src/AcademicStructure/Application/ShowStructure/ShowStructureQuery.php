<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\ShowStructure;

use AulaX\AcademicCalendar\Application\ListSchoolYears\ListSchoolYearsHandler;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearView;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\Section;
use AulaX\Catalogs\Application\Subjects;
use AulaX\Tenancy\Application\InstitutionSettings\InstitutionSettingsQuery;

/**
 * The structure of a school year for whoever may see it (EAC-R08.1): the
 * chosen year, or by default the active one, else the planned one, else the
 * newest.
 */
final readonly class ShowStructureQuery
{
    private const array DEFAULT_ORDER = ['active', 'planned'];

    public function __construct(
        private StructureLoader $loader,
        private ListSchoolYearsHandler $years,
        private InstitutionSettingsQuery $settings,
        private Subjects $subjects,
        private StructurePermissions $permissions,
    ) {}

    public function __invoke(?int $schoolYearId): StructureView
    {
        StructureAccess::ensureCanView($this->permissions);
        $years = ($this->years)();
        $levels = ($this->settings)()->levels;
        $chosen = $schoolYearId ?? $this->defaultYear($years);

        $grades = array_map(fn (Grade $grade) => [
            'code' => $grade->value,
            'name' => $grade->name(),
            'level' => $grade->level(),
            'stage' => $grade->stage()->value,
            'capacityCap' => $grade->stage()->capacityCap(),
        ], Grade::ofLevels($levels));
        $subjectOptions = [];

        foreach ($levels as $level) {
            $subjectOptions[$level] = $this->subjects->ofLevel($level);
        }

        $yearList = array_map(fn (SchoolYearView $year) => ['id' => $year->id, 'name' => $year->name, 'status' => $year->status], $years);

        if ($chosen === null) {
            return new StructureView(null, null, $yearList, $grades, [], [], $subjectOptions);
        }

        $structure = $this->loader->forYear($chosen);
        $names = [];

        foreach ($subjectOptions as $options) {
            foreach ($options as $subject) {
                $names[$subject['id']] = $subject['name'];
            }
        }

        $sections = $structure->sections();
        usort($sections, fn (Section $a, Section $b) => [array_search($a->grade(), Grade::cases(), true), $a->letter()->value]
            <=> [array_search($b->grade(), Grade::cases(), true), $b->letter()->value]);

        return new StructureView(
            $chosen,
            $structure->status()->value,
            $yearList,
            $grades,
            array_map(fn (Section $section) => new SectionView(
                (int) $section->id(),
                $section->grade()->value,
                $section->grade()->name(),
                $section->letter()->value,
                $section->capacity()->value,
                $section->capacity()->reason,
                $section->guideAccountId(),
                $section->teachers(),
            ), $sections),
            array_map(
                fn (array $subjectIds) => array_map(fn (int $id) => ['id' => $id, 'name' => $names[$id] ?? ''], $subjectIds),
                $structure->curricula(),
            ),
            $subjectOptions,
        );
    }

    /**
     * @param  list<SchoolYearView>  $years  Newest first.
     */
    private function defaultYear(array $years): ?int
    {
        foreach (self::DEFAULT_ORDER as $status) {
            foreach ($years as $year) {
                if ($year->status === $status) {
                    return $year->id;
                }
            }
        }

        return $years[0]->id ?? null;
    }
}
