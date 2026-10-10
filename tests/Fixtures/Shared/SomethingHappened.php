<?php

namespace Tests\Fixtures\Shared;

use AulaX\Shared\Domain\DomainEvent;
use DateTimeImmutable;

final readonly class SomethingHappened implements DomainEvent
{
    public function __construct(public string $what) {}

    public function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-10-10 08:00:00');
    }
}
