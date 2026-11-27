<?php

declare(strict_types=1);

namespace AulaX\Shared\Application;

use AulaX\Shared\Domain\DomainEvent;

/**
 * Publishes domain events to whoever listens (other modules, side effects such
 * as emails). Inside a transaction, publishing waits for the commit: a write
 * that rolls back must not have already sent its email.
 */
interface EventBus
{
    public function publish(DomainEvent ...$events): void;
}
