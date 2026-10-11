<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure;

use AulaX\Tenancy\Application\TenantActivationHook;
use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\Persistence\RowLevelSecurity;

final readonly class PostgresTenantActivator implements TenantActivator
{
    /**
     * @param  iterable<TenantActivationHook>  $hooks
     */
    public function __construct(
        private InMemoryCurrentTenant $currentTenant,
        private iterable $hooks,
    ) {}

    public function activate(TenantContext $context): void
    {
        $this->currentTenant->set($context);
        RowLevelSecurity::setInstitution($context->institutionId->value);

        foreach ($this->hooks as $hook) {
            $hook->onActivated($context);
        }
    }

    public function deactivate(): void
    {
        $this->currentTenant->clear();
        RowLevelSecurity::clearInstitution();

        foreach ($this->hooks as $hook) {
            $hook->onDeactivated();
        }
    }
}
