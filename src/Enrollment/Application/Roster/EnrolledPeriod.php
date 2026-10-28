<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class EnrolledPeriod
{
    public function __construct(
        public int $studentId,
        public int $schoolYearId,
        public PeriodView $period,
    ) {}
}
