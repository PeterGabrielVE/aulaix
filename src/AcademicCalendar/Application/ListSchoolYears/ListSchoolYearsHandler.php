<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\ListSchoolYears;

/**
 * The years of the institution with their dates, states and terms, for the
 * calendar screen (CAL-R06.1).
 */
final readonly class ListSchoolYearsHandler
{
    public function __construct(private SchoolYearList $list) {}

    /**
     * @return list<SchoolYearView>
     */
    public function __invoke(): array
    {
        return $this->list->all();
    }
}
