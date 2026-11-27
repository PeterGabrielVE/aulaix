<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

/**
 * A transfer request as one of its two institutions sees it.
 */
final readonly class TransferView
{
    /**
     * @param  ?string  $studentName  Only for the origin: the destination learns nothing of the student before the answer (EST-R07.2).
     * @param  ?string  $originName  Once answered.
     * @param  string  $status  pending, approved, rejected or expired.
     */
    public function __construct(
        public int $id,
        public string $identification,
        public ?string $studentName,
        public string $destinationName,
        public ?string $originName,
        public string $status,
        public string $requestedOn,
        public string $lastDayToAnswer,
        public ?string $rejectionReason,
    ) {}
}
