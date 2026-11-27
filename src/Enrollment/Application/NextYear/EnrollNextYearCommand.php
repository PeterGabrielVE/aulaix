<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\NextYear;

final readonly class EnrollNextYearCommand
{
    /**
     * @param  array<int, int>  $placements  Student id => section of the next year.
     * @param  array<int, string>  $overCapacityReasons  Section id => why it goes over its capacity.
     * @param  int  $actorId  The account signed in.
     */
    public function __construct(
        public int $fromSchoolYearId,
        public array $placements,
        public array $overCapacityReasons,
        public int $actorId,
    ) {}
}
