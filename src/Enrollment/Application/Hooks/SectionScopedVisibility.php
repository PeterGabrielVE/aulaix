<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Hooks;

use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Application\Port\StudentVisibilityRule;

/**
 * Students' extension point (ADR 0012): whoever manages students or
 * enrollments sees everyone (MAT-R08.4); anyone else, the students with a
 * current enrollment of the active year in the sections where they teach or
 * guide, or in the grades they coordinate (MAT-R08.1). Worked out each
 * time it is asked, so leaving a section hides its students at once
 * (MAT-R08.3).
 */
final readonly class SectionScopedVisibility implements StudentVisibilityRule
{
    public function __construct(
        private EnrollmentPermissions $permissions,
        private EnrollmentList $enrollments,
        private StructureFacts $structure,
        private CalendarFacts $calendar,
    ) {}

    public function visibleStudents(): ?array
    {
        $account = $this->permissions->currentAccountId();

        // No one signed in (console, queue): the permission check of the use
        // case is what stands; there are no sections to narrow to.
        if ($account === null || $this->permissions->seesAllStudents()) {
            return null;
        }

        $year = $this->calendar->activeYear();

        if ($year === null) {
            return [];
        }

        try {
            $grades = $this->structure->coordinatedGrades($account);
        } catch (AuthorizationDenied) {
            $grades = [];
        }

        return $this->enrollments->currentStudentsIn($year, $this->structure->taughtSections($account), $grades);
    }
}
