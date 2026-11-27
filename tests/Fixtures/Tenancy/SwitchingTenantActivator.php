<?php

namespace Tests\Fixtures\Tenancy;

use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\InMemoryCurrentTenant;

/**
 * Activates institutions on an in-memory current tenant, remembering the
 * ones it activated.
 */
final class SwitchingTenantActivator implements TenantActivator
{
    /** @var list<int> */
    public array $activated = [];

    public function __construct(private readonly InMemoryCurrentTenant $tenant) {}

    public function activate(TenantContext $context): void
    {
        $this->activated[] = $context->institutionId->value;
        $this->tenant->set($context);
    }

    public function deactivate(): void
    {
        $this->tenant->clear();
    }

    public function forRequest(TenantContext $context, callable $handle, callable $keep): mixed
    {
        $this->activate($context);

        return $handle();
    }

    public function within(TenantContext $context, callable $operation): mixed
    {
        $previous = $this->tenant->context();
        $this->activate($context);

        try {
            return $operation();
        } finally {
            $previous === null ? $this->deactivate() : $this->activate($previous);
        }
    }
}
