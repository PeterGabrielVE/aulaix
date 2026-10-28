<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class PeriodView
{
    /**
     * @param  string  $startsOn  Y-m-d
     * @param  ?string  $endsOn  Y-m-d, the day it ended, itself excluded.
     */
    public function __construct(
        public int $sectionId,
        public string $grade,
        public string $startsOn,
        public ?string $endsOn,
    ) {}
}
