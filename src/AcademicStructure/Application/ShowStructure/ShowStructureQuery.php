<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\ShowStructure;

use AulaX\AcademicCalendar\Application\ListSchoolYears\ListSchoolYearsHandler;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearView;
use AulaX\AcademicStructure\Application\Port\StaffDirectory;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\StructureAccess;
use AulaX\AcademicStructure\Application\StructureLoader;
use AulaX\AcademicStructure\Domain\CoordinatorAssignment;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructure;
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
        private StaffDirectory $staff,
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

        $people = $this->staff->describe($this->assignedPeople($structure));

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
            array_map(fn (CoordinatorAssignment $coordinator) => [
                'accountId' => $coordinator->accountId,
                'level' => $coordinator->level,
                'grades' => array_map(fn (Grade $grade) => $grade->value, $coordinator->grades),
            ], $structure->coordinators()),
            $people,
            $this->orphans($sections, $people),
            $this->staff->candidates(StaffDirectory::TEACHER),
            $this->staff->candidates(StaffDirectory::COORDINATOR),
        );
    }

    /**
     * @return list<int>
     */
    private function assignedPeople(SchoolYearStructure $structure): array
    {
        $people = array_map(fn (CoordinatorAssignment $coordinator) => $coordinator->accountId, $structure->coordinators());

        foreach ($structure->sections() as $section) {
            $people = [...$people, ...array_values($section->teachers())];

            if ($section->guideAccountId() !== null) {
                $people[] = $section->guideAccountId();
            }
        }

        return array_values(array_unique($people));
    }

    /**
     * The assignments kept for people who are no longer active members: the
     * institution reassigns them by hand (EAC-R05.6, R08.5).
     *
     * @param  list<Section>  $sections
     * @param  array<int, array{name: string, active: bool}>  $people
     * @return list<array{sectionId: int, subjectId: ?int, accountId: int}>
     */
    private function orphans(array $sections, array $people): array
    {
        $orphans = [];

        foreach ($sections as $section) {
            if ($section->guideAccountId() !== null && ! $people[$section->guideAccountId()]['active']) {
                $orphans[] = ['sectionId' => (int) $section->id(), 'subjectId' => null, 'accountId' => $section->guideAccountId()];
            }

            foreach ($section->teachers() as $subjectId => $accountId) {
                if (! $people[$accountId]['active']) {
                    $orphans[] = ['sectionId' => (int) $section->id(), 'subjectId' => $subjectId, 'accountId' => $accountId];
                }
            }
        }

        return $orphans;
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
