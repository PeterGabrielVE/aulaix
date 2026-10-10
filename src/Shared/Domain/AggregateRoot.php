<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

/**
 * Lets an aggregate record domain events for its use case to publish. A trait
 * rather than a base class, so aggregates stay free to extend nothing.
 */
trait AggregateRoot
{
    /**
     * @var list<DomainEvent>
     */
    private array $recordedEvents = [];

    protected function recordThat(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Hand over the events recorded so far, oldest first, and forget them.
     *
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
