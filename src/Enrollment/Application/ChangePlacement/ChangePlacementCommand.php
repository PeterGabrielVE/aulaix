<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\ChangePlacement;

final readonly class ChangePlacementCommand
{
    /**
     * @param  ?string  $date  Y-m-d
     * @param  int  $actorId  The account signed in: who moves the student and, in a change of grade, approves it.
     */
    public function __construct(
        public int $enrollmentId,
        public int $sectionId,
        public ?string $date,
        public ?string $reason,
        public ?string $overCapacityReason,
        public ?string $ageExceptionJustification,
        public int $actorId,
    ) {}
}
