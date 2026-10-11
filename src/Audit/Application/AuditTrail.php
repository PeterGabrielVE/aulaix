<?php

declare(strict_types=1);

namespace AulaX\Audit\Application;

/**
 * Records auditable changes (ADR 0005). Call it inside the use case's
 * TransactionManager::run(), so the change and its record are committed
 * together or not at all (AUD-R02).
 */
interface AuditTrail
{
    /**
     * Into the active institution's log (AUD-R04.1).
     */
    public function record(AuditEvent $event): void;

    /**
     * Into the platform log, for changes to the global identity that belong
     * to no institution (AUD-R04.2). Only possible with no institution active.
     */
    public function recordForPlatform(AuditEvent $event): void;
}
