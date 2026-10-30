<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicStructure\Application\Grades\CurriculumQuery;
use AulaX\AcademicStructure\Application\Grades\GradesQuery;
use AulaX\AcademicStructure\Application\Grades\GradeView;
use AulaX\AcademicStructure\Application\MyAssignments\MyAssignmentsQuery;
use AulaX\AcademicStructure\Application\Overview\CoordinatedGradesQuery;
use AulaX\AcademicStructure\Application\Port\StaffDirectory;
use AulaX\AcademicStructure\Application\SectionsOfYear\SectionsOfYearQuery;
use AulaX\AcademicStructure\Application\SectionsOfYear\SectionSummary;
use AulaX\AcademicStructure\Application\ShowStructure\ShowStructureQuery;
use AulaX\AcademicStructure\Application\TeachingQuery;
use AulaX\Catalogs\Application\Subjects;
use AulaX\Evaluation\Application\Port\ClassFact;
use AulaX\Evaluation\Application\Port\SectionInfo;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * Teaching, sections and curricula, through AcademicStructure's public
 * queries (A-06).
 */
final readonly class AcademicStructureTeachingFacts implements TeachingFacts
{
    public function __construct(
        private TeachingQuery $teaching,
        private CoordinatedGradesQuery $coordinated,
        private SectionsOfYearQuery $sections,
        private GradesQuery $grades,
        private CurriculumQuery $curriculum,
        private MyAssignmentsQuery $assignments,
        private Subjects $subjects,
        private ShowStructureQuery $structure,
        private StaffDirectory $staff,
    ) {}

    public function teaches(int $accountId, int $sectionId, int $subjectId): bool
    {
        return $this->teaching->teaches($accountId, $sectionId, $subjectId);
    }

    public function guides(int $accountId, int $sectionId): bool
    {
        return $this->teaching->guides($accountId, $sectionId);
    }

    public function coordinatedGrades(int $accountId): array
    {
        try {
            return ($this->coordinated)($accountId);
        } catch (AuthorizationDenied) {
            // Without ver-estructura (a Representante), it coordinates nothing.
            return [];
        }
    }

    public function section(int $schoolYearId, int $sectionId): ?SectionInfo
    {
        $matches = array_values(array_filter($this->sectionsOf($schoolYearId), fn (SectionInfo $section) => $section->id === $sectionId));

        return $matches[0] ?? null;
    }

    public function sectionsOf(int $schoolYearId): array
    {
        try {
            $sections = ($this->sections)($schoolYearId);
        } catch (SchoolYearNotFound) {
            return [];
        }

        $grades = $this->gradesByCode();

        return array_map(fn (SectionSummary $section) => new SectionInfo(
            $section->id,
            $schoolYearId,
            $section->grade,
            $grades[$section->grade]->name,
            $grades[$section->grade]->level,
            $section->letter,
        ), $sections);
    }

    public function curriculum(int $schoolYearId, string $grade): array
    {
        return ($this->curriculum)($schoolYearId, $grade);
    }

    public function subjectNames(string $level): array
    {
        return array_column($this->subjects->ofLevel($level), 'name', 'id');
    }

    public function taughtClasses(int $accountId): array
    {
        $assignments = ($this->assignments)($accountId);

        if ($assignments->schoolYearId === null) {
            return [];
        }

        $taught = array_map(fn (array $class) => "{$class['sectionId']}:{$class['subjectId']}", $assignments->teaching);

        return array_values(array_filter(
            $this->classesOf($assignments->schoolYearId),
            fn (ClassFact $class) => in_array("{$class->sectionId}:{$class->subjectId}", $taught, true),
        ));
    }

    public function classesOf(int $schoolYearId): array
    {
        try {
            $sections = ($this->sections)($schoolYearId);
        } catch (SchoolYearNotFound) {
            return [];
        }

        $grades = $this->gradesByCode();
        $names = [];
        $curricula = [];
        $classes = [];

        foreach ($sections as $section) {
            $grade = $grades[$section->grade];
            $names[$grade->level] ??= $this->subjectNames($grade->level);
            $curricula[$grade->code] ??= ($this->curriculum)($schoolYearId, $grade->code);

            foreach ($curricula[$grade->code] as $subjectId) {
                $classes[] = new ClassFact(
                    $section->id,
                    $schoolYearId,
                    "{$grade->name} {$section->letter}",
                    $grade->code,
                    $grade->level,
                    $subjectId,
                    $names[$grade->level][$subjectId] ?? '',
                );
            }
        }

        return $classes;
    }

    public function staffNames(int $schoolYearId): array
    {
        try {
            $structure = ($this->structure)($schoolYearId);
        } catch (AuthorizationDenied|SchoolYearNotFound) {
            return ['teachers' => [], 'guides' => []];
        }

        $name = fn (int $account) => $structure->people[$account]['name'] ?? '';
        $teachers = [];
        $guides = [];

        foreach ($structure->sections as $section) {
            $teachers[$section->id] = array_map($name, $section->teachers);

            if ($section->guideAccountId !== null) {
                $guides[$section->id] = $name($section->guideAccountId);
            }
        }

        return ['teachers' => $teachers, 'guides' => $guides];
    }

    /**
     * @return array<string, GradeView>
     */
    private function gradesByCode(): array
    {
        return array_column(array_map(fn (GradeView $grade) => [$grade->code, $grade], ($this->grades)()), 1, 0);
    }

    public function activeTeachers(): array
    {
        return array_column($this->staff->candidates(StaffDirectory::TEACHER), 'name', 'id');
    }

    public function gradeNames(): array
    {
        return array_map(fn (GradeView $grade) => $grade->name, $this->gradesByCode());
    }
}
