<?php

namespace Tests\Fixtures\Shared;

use AulaX\Shared\Domain\Clock;
use DateTimeImmutable;

/**
 * A clock stopped at a given moment, moved by hand.
 */
final class FixedClock implements Clock
{
    public function __construct(public DateTimeImmutable $now = new DateTimeImmutable('2026-10-21 10:00:00')) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
