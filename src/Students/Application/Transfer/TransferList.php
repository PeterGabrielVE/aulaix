<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

use AulaX\Shared\Domain\InstitutionId;
use DateTimeImmutable;

/**
 * Read side of the transfers the open institution can see (ADR 0008).
 */
interface TransferList
{
    /**
     * Newest first; statuses as of $now, so an unanswered request past its
     * days reads expired.
     */
    public function boardOf(InstitutionId $institution, DateTimeImmutable $now): TransferBoard;
}
