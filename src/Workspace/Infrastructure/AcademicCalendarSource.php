<?php

declare(strict_types=1);

namespace AulaX\Workspace\Infrastructure;

use AulaX\AcademicCalendar\Application\CalendarContext\CalendarContextQuery;
use AulaX\Workspace\Application\CalendarSnapshot;
use AulaX\Workspace\Application\Port\CalendarSource;

/**
 * Through AcademicCalendar's public query (A-06), asked fresh every time
 * (NAV-R02.8).
 */
final readonly class AcademicCalendarSource implements CalendarSource
{
    public function __construct(private CalendarContextQuery $calendar) {}

    public function snapshot(): CalendarSnapshot
    {
        $context = ($this->calendar)();

        return new CalendarSnapshot($context->activeYearName, $context->openTerm, $context->hasPlannedYear);
    }
}
