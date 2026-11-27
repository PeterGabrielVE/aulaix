<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Port;

use AulaX\Shared\Domain\Conflict;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Application\Transfer\StudentTransferData;

/**
 * Writes an approved transfer's record in the destination institution, from
 * the origin's transaction (ADR 0008). The only place that acts as another
 * institution; afterwards the open institution is the origin again.
 */
interface TransferCopier
{
    /**
     * Creates the student, active, with its guardians: the destination's own
     * when it already has them by document, new ones without account
     * otherwise (EST-R07.5, R07.8). Recorded in the destination's audit log.
     *
     * @throws Conflict When the destination already has a student with that identification.
     */
    public function copyTo(InstitutionId $destination, StudentTransferData $data): void;
}
