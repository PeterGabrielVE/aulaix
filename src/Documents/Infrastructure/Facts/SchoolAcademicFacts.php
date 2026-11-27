<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Facts;

use AulaX\AcademicCalendar\Application\ListSchoolYears\ListSchoolYearsHandler;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearView;
use AulaX\AcademicCalendar\Application\ListSchoolYears\TermView;
use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicStructure\Application\Grades\GradesQuery;
use AulaX\AcademicStructure\Application\Grades\GradeView;
use AulaX\AcademicStructure\Application\Overview\CoordinatedGradesQuery;
use AulaX\AcademicStructure\Application\SectionsOfYear\SectionsOfYearQuery;
use AulaX\AcademicStructure\Application\SectionsOfYear\SectionSummary;
use AulaX\Documents\Application\Port\AcademicFacts;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * Reads school years, terms, sections and Coordinator grades through the
 * public Application APIs of AcademicCalendar and AcademicStructure (A-06).
 */
final readonly class SchoolAcademicFacts implements AcademicFacts
{
    public function __construct(
        private ListSchoolYearsHandler $years,
        private SchoolYearLookup $yearLookup,
        private SectionsOfYearQuery $sections,
        private GradesQuery $grades,
        private CoordinatedGradesQuery $coordinated,
    ) {}

    public function activeSchoolYearId(): ?int
    {
        return $this->yearLookup->activeYear();
    }

    public function schoolYearName(int $schoolYearId): ?string
    {
        foreach (($this->years)() as $year) {
            if ($year->id === $schoolYearId) {
                return $year->name;
            }
        }

        return null;
    }

    public function schoolYears(): array
    {
        return array_map(
            fn (SchoolYearView $year) => ['id' => $year->id, 'name' => $year->name, 'status' => $year->status],
            ($this->years)(),
        );
    }

    public function termStatus(int $schoolYearId, int $term): ?string
    {
        return $this->term($schoolYearId, $term)?->status;
    }

    public function termEndDate(int $schoolYearId, int $term): ?string
    {
        return $this->term($schoolYearId, $term)?->endsOn;
    }

    public function section(int $schoolYearId, int $sectionId): ?array
    {
        try {
            $sections = ($this->sections)($schoolYearId);
        } catch (SchoolYearNotFound) {
            return null;
        }

        $match = array_values(array_filter($sections, fn (SectionSummary $section) => $section->id === $sectionId))[0] ?? null;

        if ($match === null) {
            return null;
        }

        $gradesByCode = array_column(array_map(fn (GradeView $grade) => [$grade->code, $grade], ($this->grades)()), 1, 0);
        $grade = $gradesByCode[$match->grade] ?? null;

        if ($grade === null) {
            return null;
        }

        return [
            'id' => $match->id,
            'schoolYearId' => $schoolYearId,
            'gradeCode' => $grade->code,
            'gradeName' => $grade->name,
            'level' => $grade->level,
            'letter' => $match->letter,
        ];
    }

    public function coordinatedGrades(int $accountId): array
    {
        try {
            return ($this->coordinated)($accountId);
        } catch (AuthorizationDenied) {
            return [];
        }
    }

    private function term(int $schoolYearId, int $term): ?TermView
    {
        foreach (($this->years)() as $year) {
            if ($year->id !== $schoolYearId) {
                continue;
            }

            foreach ($year->terms as $termView) {
                if ($termView->number === $term) {
                    return $termView;
                }
            }
        }

        return null;
    }
}
