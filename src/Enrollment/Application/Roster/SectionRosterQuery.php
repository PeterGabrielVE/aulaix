<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The students currently enrolled in a section, by name, with the figures
 * of the section (MAT-R02.3, R09.1). Also the public API for SPEC-011 and
 * SPEC-012 (MAT-R09.3).
 */
final readonly class SectionRosterQuery
{
    public function __construct(
        private EnrollmentList $enrollments,
        private StudentFacts $students,
        private StructureFacts $structure,
        private EnrollmentPermissions $permissions,
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws SchoolYearNotFound
     * @throws SectionNotFound
     */
    public function __invoke(int $schoolYearId, int $sectionId): SectionRoster
    {
        EnrollmentAccess::ensureCanView($this->permissions);
        $matches = array_values(array_filter($this->structure->sectionsOf($schoolYearId), fn (SectionFact $section) => $section->id === $sectionId));
        $section = $matches[0] ?? throw SectionNotFound::create();
        $enrollments = $this->enrollments->currentIn($sectionId);
        $students = $this->students->byIds(array_map(fn (Enrollment $enrollment) => $enrollment->studentId(), $enrollments));
        $roster = [];

        foreach ($enrollments as $enrollment) {
            $student = $students[$enrollment->studentId()] ?? null;

            if ($student !== null) {
                $roster[] = new RosterStudent(
                    (int) $enrollment->id()?->value,
                    $student->id,
                    $student->fullName,
                    $student->identification,
                    $enrollment->period()->startsOn->format('Y-m-d'),
                    $enrollment->overCapacityReason(),
                );
            }
        }

        return new SectionRoster(
            $schoolYearId,
            Overviews::section($section, count($enrollments)),
            Overviews::byName($roster, fn (RosterStudent $student) => $student->fullName),
        );
    }
}
