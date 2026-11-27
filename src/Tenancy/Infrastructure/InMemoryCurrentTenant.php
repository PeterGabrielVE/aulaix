<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure;

use AulaX\Shared\Domain\InstitutionId;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\TenantContext;

/**
 * Holds the tenant for the lifetime of one request. Bound as a singleton, and
 * the CurrentTenant port is an alias of it, so replacing this instance in the
 * container replaces what every reader of the port sees.
 */
final class InMemoryCurrentTenant implements CurrentTenant
{
    private ?TenantContext $context = null;

    public function set(TenantContext $context): void
    {
        $this->context = $context;
    }

    public function clear(): void
    {
        $this->context = null;
    }

    public function context(): ?TenantContext
    {
        return $this->context;
    }

    public function institutionId(): ?InstitutionId
    {
        return $this->context?->institutionId;
    }
}
