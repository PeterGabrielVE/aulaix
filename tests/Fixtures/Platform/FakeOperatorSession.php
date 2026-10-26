<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\OperatorSession;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorId;
use DateTimeImmutable;

/**
 * In-memory stand-in for the Platform port of the same role (SPEC-014 tests).
 */
final class FakeOperatorSession implements OperatorSession
{
    public ?OperatorId $operator = null;

    public ?int $version = null;

    public ?DateTimeImmutable $confirmedAt = null;

    public int $starts = 0;

    public function start(Operator $operator, DateTimeImmutable $now): void
    {
        $this->operator = $operator->id;
        $this->version = $operator->sessionVersion;
        $this->confirmedAt = $now;
        $this->starts++;
    }

    public function end(): void
    {
        $this->operator = null;
        $this->version = null;
        $this->confirmedAt = null;
    }

    public function current(): ?OperatorId
    {
        return $this->operator;
    }

    public function confirmTotp(DateTimeImmutable $now): void
    {
        $this->confirmedAt = $now;
    }

    public function totpConfirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function sessionVersion(): ?int
    {
        return $this->version;
    }

    public ?DateTimeImmutable $lastActivity = null;

    public function touch(DateTimeImmutable $now): void
    {
        $this->lastActivity = $now;
    }

    public function lastActivityAt(): ?DateTimeImmutable
    {
        return $this->lastActivity;
    }
}
