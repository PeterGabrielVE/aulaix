<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

/**
 * Lets another module react when a tenant is activated (e.g. scoping the
 * permission registry to it) without Tenancy depending on that module.
 * Implementations are tagged with this interface in the container.
 */
interface TenantActivationHook
{
    public function onActivated(TenantContext $context): void;

    public function onDeactivated(): void;
}
