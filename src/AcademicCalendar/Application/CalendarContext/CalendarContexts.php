<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\CalendarContext;

/**
 * Read side of the calendar context (A-08): asked on every page of the
 * institution, so it answers with one query and is never cached (NAV-R02.8).
 */
interface CalendarContexts
{
    public function current(): CalendarContext;
}
