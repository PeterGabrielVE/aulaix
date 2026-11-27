<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application\ListSchoolYears;

final readonly class SchoolYearView
{
    /**
     * @param  string  $startsOn  Y-m-d
     * @param  string  $endsOn  Y-m-d
     * @param  string  $status  planned | active | closed
     * @param  list<TermView>  $terms  Terms 1 to 3.
     */
    public function __construct(
        public int $id,
        public string $name,
        public string $startsOn,
        public string $endsOn,
        public string $status,
        public array $terms,
    ) {}
}
