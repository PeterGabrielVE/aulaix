<?php

namespace Tests\Fixtures\Workspace;

use AulaX\Workspace\Application\CalendarSnapshot;
use AulaX\Workspace\Application\Port\CalendarSource;

/**
 * The calendar of a test.
 */
final class FixedCalendarSource implements CalendarSource
{
    public function __construct(public CalendarSnapshot $snapshot) {}

    public function snapshot(): CalendarSnapshot
    {
        return $this->snapshot;
    }
}
