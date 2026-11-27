<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure\Queue;

use AulaX\Tenancy\Application\TenantActivator;
use AulaX\Tenancy\Application\TenantLeak;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Queue\Events\JobProcessing;

/**
 * Every job starts with no institution (SPEC-015 RLT-R03.1). The
 * institution only lives in a transaction (ADR 0011), so the one thing a
 * previous job can leave behind on a long-lived worker is a transaction it
 * never closed: it is rolled back, and reported, since it means a bug
 * elsewhere.
 */
final readonly class EnsureNoTenantOnJobStart
{
    public function __construct(
        private ConnectionInterface $connection,
        private TenantActivator $activator,
    ) {}

    public function handle(JobProcessing $event): void
    {
        if ($this->connection->transactionLevel() > 0) {
            report(new TenantLeak("A job started with {$this->connection->transactionLevel()} transaction(s) left open; rolled back."));
            $this->connection->rollBack(0);
        }

        $this->activator->deactivate();
    }
}
