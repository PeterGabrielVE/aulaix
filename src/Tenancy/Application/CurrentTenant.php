<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

use AulaX\Shared\Domain\InstitutionId;

/**
 * The tenant resolved for the current request, read-only. Only the
 * TenantActivator sets it, together with the database session (RLS), so the
 * two can never disagree.
 */
interface CurrentTenant
{
    public function context(): ?TenantContext;

    public function institutionId(): ?InstitutionId;
}
