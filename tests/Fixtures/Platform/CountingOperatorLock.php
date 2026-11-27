<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\OperatorLock;
use AulaX\Platform\Domain\OperatorId;

/**
 * Records the exact order of advisory locks acquired by a use case
 * (ADR 0014, SPEC-017).
 */
final class CountingOperatorLock implements OperatorLock
{
    /** @var list<string> */
    public array $events = [];

    public int $rosterLocks = 0;

    /** @var list<int> */
    public array $operatorLocks = [];

    /** @var ?callable(): void */
    public $onLock = null;

    public function lockOperator(OperatorId $id): void
    {
        $this->operatorLocks[] = $id->value;
        $this->events[] = "operator:{$id->value}";

        if ($this->onLock !== null) {
            ($this->onLock)();
        }
    }

    public function lockRoster(): void
    {
        $this->rosterLocks++;
        $this->events[] = 'roster';
    }
}
