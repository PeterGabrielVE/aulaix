<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use AulaX\Shared\Domain\InstitutionId;

/**
 * Transaction-scoped advisory lock on an institution's active/inactive status
 * (ADR 0014, SPEC-017 RSP-R08): shared for writes targeting an institution
 * from outside its subdomain (transfers, queued jobs), exclusive for platform
 * status transitions.
 */
interface InstitutionStatusLock
{
    public function shared(InstitutionId $id): void;

    public function exclusive(InstitutionId $id): void;
}
