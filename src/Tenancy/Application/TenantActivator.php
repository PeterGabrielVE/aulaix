<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application;

/**
 * Turns "an institution" into "the tenant" for the rest of the request or
 * job: the CurrentTenant port, the database session that Row Level Security
 * reads, and whatever other modules hook onto it. One place, so they can
 * never disagree.
 */
interface TenantActivator
{
    public function activate(TenantContext $context): void;

    /**
     * Leave the tenant: for long-lived processes (queue workers, commands
     * looping over institutions) that reuse one database connection.
     */
    public function deactivate(): void;
}
