<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class RosterStudent
{
    /**
     * @param  string  $startsOn  Y-m-d
     */
    public function __construct(
        public int $enrollmentId,
        public int $studentId,
        public string $fullName,
        public string $identification,
        public string $startsOn,
        public ?string $overCapacityReason,
    ) {}
}
