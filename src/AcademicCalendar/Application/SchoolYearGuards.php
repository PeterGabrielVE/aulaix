<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application;

use AulaX\AcademicCalendar\Application\Port\SchoolYearGuard;

/**
 * Every registered SchoolYearGuard, asked together.
 */
final readonly class SchoolYearGuards
{
    /**
     * @param  iterable<SchoolYearGuard>  $guards
     */
    public function __construct(private iterable $guards) {}

    public function unresolvedResults(int $schoolYearId): int
    {
        $unresolved = 0;

        foreach ($this->guards as $guard) {
            $unresolved += $guard->unresolvedResults($schoolYearId);
        }

        return $unresolved;
    }

    public function hasEnrollments(int $schoolYearId): bool
    {
        foreach ($this->guards as $guard) {
            if ($guard->hasEnrollments($schoolYearId)) {
                return true;
            }
        }

        return false;
    }
}
