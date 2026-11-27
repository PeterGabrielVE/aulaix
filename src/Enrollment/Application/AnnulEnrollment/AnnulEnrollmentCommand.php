<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\AnnulEnrollment;

final readonly class AnnulEnrollmentCommand
{
    /**
     * @param  ?string  $reason  typing_error, duplicate or other.
     * @param  int  $actorId  The account signed in.
     */
    public function __construct(
        public int $enrollmentId,
        public ?string $reason,
        public ?string $note,
        public int $actorId,
    ) {}
}
