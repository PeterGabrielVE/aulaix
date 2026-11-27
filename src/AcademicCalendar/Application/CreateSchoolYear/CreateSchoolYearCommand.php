<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\CreateSchoolYear;

final readonly class CreateSchoolYearCommand
{
    /**
     * @param  string  $startsOn  Y-m-d
     * @param  string  $endsOn  Y-m-d
     * @param  list<array{0: string, 1: string}>  $terms  Start and end (Y-m-d) of terms 1 to 3.
     */
    public function __construct(
        public string $startsOn,
        public string $endsOn,
        public array $terms,
    ) {}
}
