<?php

declare(strict_types=1);

namespace AulaX\Shared\Infrastructure;

use AulaX\Shared\Application\EventBus;
use AulaX\Shared\Domain\DomainEvent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;

/**
 * Dispatches through Laravel's event system, so listeners (queued or not) are
 * plain Laravel listeners. afterCommit() runs the dispatch right away when no
 * transaction is open, and drops it when the open transaction rolls back.
 */
final class LaravelEventBus implements EventBus
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly Connection $connection,
    ) {}

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->connection->afterCommit(fn () => $this->events->dispatch($event));
        }
    }
}
