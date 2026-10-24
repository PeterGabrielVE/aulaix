<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application;

/**
 * Serializes the calendar writes of the active institution (ADR 0007): two
 * managers acting at once queue up instead of both passing the checks
 * across years. Taken inside the use case's transaction, released with it.
 */
interface CalendarLock
{
    public function lockInstitutionCalendar(): void;
}
