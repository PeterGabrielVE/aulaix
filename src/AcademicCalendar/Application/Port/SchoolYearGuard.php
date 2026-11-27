<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\Port;

/**
 * An extension point for the modules that build on the calendar (ADR 0012):
 * SPEC-010 does not let a year close while students lack their result
 * (MAT-R16.2), nor a planned year be deleted once it has enrollments.
 * Asked under the calendar lock, inside the change's transaction. Until a
 * module registers one, nothing blocks.
 */
interface SchoolYearGuard
{
    /**
     * The container tag a module puts its guard under.
     */
    public const string TAG = 'academic_calendar.school_year_guards';

    /**
     * How many students of the year still lack their result.
     */
    public function unresolvedResults(int $schoolYearId): int;

    public function hasEnrollments(int $schoolYearId): bool;
}
