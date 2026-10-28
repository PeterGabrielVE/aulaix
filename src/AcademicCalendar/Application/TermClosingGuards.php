<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application;

use AulaX\AcademicCalendar\Application\Port\TermClosingGuard;

/**
 * Every registered TermClosingGuard, asked together.
 */
final readonly class TermClosingGuards
{
    /**
     * @param  iterable<TermClosingGuard>  $guards
     */
    public function __construct(private iterable $guards) {}

    public function pendingGrades(int $schoolYearId, int $term): int
    {
        $pending = 0;

        foreach ($this->guards as $guard) {
            $pending += $guard->pendingGrades($schoolYearId, $term);
        }

        return $pending;
    }

    public function fillPending(int $schoolYearId, int $term, string $justification): void
    {
        foreach ($this->guards as $guard) {
            $guard->fillPending($schoolYearId, $term, $justification);
        }
    }
}
