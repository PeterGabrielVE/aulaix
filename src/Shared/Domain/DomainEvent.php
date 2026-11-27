<?php

declare(strict_types=1);

namespace AulaX\Shared\Domain;

use DateTimeImmutable;

/**
 * Something that happened in the domain. Recorded by aggregates and
 * published through the EventBus port once the transaction commits.
 */
interface DomainEvent
{
    public function occurredAt(): DateTimeImmutable;
}
