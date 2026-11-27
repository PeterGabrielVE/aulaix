<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Correction;

/**
 * The corrections of the open institution (Row Level Security).
 */
interface CorrectionRequestRepository
{
    public function byId(CorrectionRequestId $id): ?CorrectionRequest;

    public function save(CorrectionRequest $request): CorrectionRequestId;
}
