<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Domain\Enrollment;

/**
 * The students who still lack the result of the year, by section (MAT-R06.10,
 * R16.1): what stands between the year and its closing. Their names only for
 * whoever may see students; the rest see how many.
 */
final readonly class PendingResultsQuery
{
    public function __construct(
        private EnrollmentList $enrollments,
        private StudentFacts $students,
        private StructureFacts $structure,
        private EnrollmentPermissions $permissions,
    ) {}

    /**
     * @return list<PendingSection> In the order of the sections; only those with someone pending.
     *
     * @throws SchoolYearNotFound
     */
    public function __invoke(int $schoolYearId): array
    {
        $bySection = [];

        foreach ($this->enrollments->currentWithoutResult($schoolYearId) as $enrollment) {
            $bySection[$enrollment->placement()->sectionId][] = $enrollment;
        }

        if ($bySection === []) {
            return [];
        }

        $names = $this->permissions->canView()
            ? array_map(fn ($student) => $student->fullName, $this->students->byIds(array_map(
                fn (Enrollment $enrollment) => $enrollment->studentId(),
                array_merge(...array_values($bySection)),
            )))
            : [];
        $pending = [];

        foreach ($this->structure->sectionsOf($schoolYearId) as $section) {
            $enrollments = $bySection[$section->id] ?? [];

            if ($enrollments !== []) {
                $pending[] = self::pendingIn($section, $enrollments, $names);
            }
        }

        return $pending;
    }

    /**
     * @param  list<Enrollment>  $enrollments
     * @param  array<int, string>  $names
     */
    private static function pendingIn(SectionFact $section, array $enrollments, array $names): PendingSection
    {
        return new PendingSection(
            $section->id,
            $section->gradeName,
            $section->letter,
            count($enrollments),
            Overviews::sortNames(array_values(array_filter(array_map(fn (Enrollment $enrollment) => $names[$enrollment->studentId()] ?? null, $enrollments)))),
        );
    }
}
