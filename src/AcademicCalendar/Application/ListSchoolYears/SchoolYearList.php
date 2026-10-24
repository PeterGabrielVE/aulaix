<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\ListSchoolYears;

/**
 * Read side of the calendar screen (A-08): the years of the active
 * institution, without loading aggregates.
 */
interface SchoolYearList
{
    /**
     * @return list<SchoolYearView> Newest first.
     */
    public function all(): array;
}
