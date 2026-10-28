<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Hooks;

use AulaX\AcademicCalendar\Application\Port\SchoolYearGuard;
use AulaX\Enrollment\Application\Port\EnrollmentList;

/**
 * AcademicCalendar's extension point (ADR 0012): a year does not close
 * while a current enrollment lacks its result (MAT-R16.2), a withdrawn
 * student no longer counting (MAT-R16.4); and a planned year with
 * enrollments is not deleted, as they keep it (RESTRICT).
 */
final readonly class UnresolvedResultsGuard implements SchoolYearGuard
{
    public function __construct(private EnrollmentList $enrollments) {}

    public function unresolvedResults(int $schoolYearId): int
    {
        return count($this->enrollments->currentWithoutResult($schoolYearId));
    }

    public function hasEnrollments(int $schoolYearId): bool
    {
        return $this->enrollments->hasAnyInYear($schoolYearId);
    }
}
