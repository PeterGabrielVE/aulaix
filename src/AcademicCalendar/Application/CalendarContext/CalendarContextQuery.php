<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\CalendarContext;

/**
 * The public API for other modules (SPEC-013): the active year and its open
 * term of the open institution. Every member may know them, so it checks no
 * permission.
 */
final readonly class CalendarContextQuery
{
    public function __construct(private CalendarContexts $contexts) {}

    public function __invoke(): CalendarContext
    {
        return $this->contexts->current();
    }
}
