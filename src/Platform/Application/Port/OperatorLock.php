<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

use AulaX\Platform\Domain\OperatorId;

/**
 * Transaction-scoped advisory locks for platform operators (ADR 0014).
 * When both locks are needed, always acquire lockRoster() before lockOperator()
 * to prevent deadlocks.
 */
interface OperatorLock
{
    public function lockOperator(OperatorId $id): void;

    public function lockRoster(): void;
}
