<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\Port;

use AulaX\Workspace\Application\CalendarSnapshot;

/**
 * The open institution's active year and open term, from AcademicCalendar.
 */
interface CalendarSource
{
    public function snapshot(): CalendarSnapshot;
}
