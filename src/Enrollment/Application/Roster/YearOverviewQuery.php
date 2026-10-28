<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\SectionFact;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Application\Enrollable\EnrollableStudent;
use AulaX\Students\Application\StudentVisibility;

/**
 * The enrollments of a school year at a glance: each section with its
 * capacity, its current enrollments and whether it is over capacity
 * (MAT-R02.3, R09.1), and the active students left to enroll (MAT-R09.2).
 * Without a year, the active one, else the newest.
 */
final readonly class YearOverviewQuery
{
    public function __construct(
        private EnrollmentList $enrollments,
        private StudentFacts $students,
        private StructureFacts $structure,
        private EnrollmentPermissions $permissions,
        private CalendarFacts $calendar,
        private StudentVisibility $visibility = new StudentVisibility([]),
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws SchoolYearNotFound
     */
    public function __invoke(?int $schoolYearId): YearOverview
    {
        EnrollmentAccess::ensureCanView($this->permissions);
        $years = $this->calendar->years();
        $schoolYearId ??= $this->calendar->activeYear() ?? ($years[0]['id'] ?? null);

        if ($schoolYearId === null) {
            return new YearOverview(null, null, [], [], []);
        }

        $status = $this->calendar->statusOf($schoolYearId) ?? throw SchoolYearNotFound::create();
        $counts = $this->enrollments->currentCountsOf($schoolYearId);
        $enrolled = array_flip($this->enrollments->currentStudentsOf($schoolYearId));
        $visible = $this->visibility->visibleStudents();

        return new YearOverview(
            $schoolYearId,
            $status,
            $years,
            array_map(fn (SectionFact $section) => Overviews::section($section, $counts[$section->id] ?? 0), $this->structure->sectionsOf($schoolYearId)),
            array_values(array_filter(
                $this->students->active(),
                fn (EnrollableStudent $student) => ! isset($enrolled[$student->id]) && ($visible === null || in_array($student->id, $visible, true)),
            )),
        );
    }
}
