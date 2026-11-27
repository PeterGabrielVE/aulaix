<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\ChangeSchoolYearDates;

final readonly class ChangeSchoolYearDatesCommand
{
    /**
     * @param  list<array{0: string, 1: string}>  $terms  Start and end (Y-m-d) of terms 1 to 3.
     */
    public function __construct(
        public int $schoolYearId,
        public string $startsOn,
        public string $endsOn,
        public array $terms,
    ) {}
}
