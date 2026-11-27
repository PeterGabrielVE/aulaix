<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\CalendarContext;

/**
 * Where the open institution stands in its calendar (SPEC-013 NAV-R02.2,
 * R02.3, R05.2): its active year, the open term of that year, and whether a
 * year is being planned.
 */
final readonly class CalendarContext
{
    /**
     * @param  ?string  $activeYearName  «2026-2027» (CAL-R01.7).
     * @param  ?int  $openTerm  1 to 3; null when no term of the active year is open.
     */
    public function __construct(
        public ?int $activeYearId,
        public ?string $activeYearName,
        public ?int $openTerm,
        public bool $hasPlannedYear,
    ) {}
}
