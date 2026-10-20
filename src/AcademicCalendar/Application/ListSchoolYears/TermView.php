<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\ListSchoolYears;

final readonly class TermView
{
    /**
     * @param  string  $status  pending | open | closed
     */
    public function __construct(
        public int $number,
        public string $startsOn,
        public string $endsOn,
        public string $status,
    ) {}
}
