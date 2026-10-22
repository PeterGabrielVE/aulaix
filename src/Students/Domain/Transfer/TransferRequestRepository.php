<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Transfer;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Domain\Identity\StudentIdentification;

/**
 * Transfer requests as the open institution sees them under ADR 0008: its
 * own, the ones it answered, and the pending ones for its active students.
 */
interface TransferRequestRepository
{
    public function byId(TransferRequestId $id): ?TransferRequest;

    /**
     * The destination's stored-pending request for this identification, if
     * any (it may be past its days).
     */
    public function pendingOf(InstitutionId $destination, StudentIdentification $identification): ?TransferRequest;

    public function save(TransferRequest $request): TransferRequestId;
}
