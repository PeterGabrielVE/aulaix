<?php

namespace Tests\Fixtures\Platform;

use AulaX\Platform\Application\Port\SecurityLog;
use AulaX\Platform\Domain\Operator;

/**
 * In-memory stand-in for the Platform port of the same role (SPEC-014 tests).
 */
final class RecordingSecurityLog implements SecurityLog
{
    /** @var list<array{operator: ?int, reason: string}> */
    public array $failures = [];

    public function loginFailed(?int $operatorId, string $reason): void
    {
        $this->failures[] = ['operator' => $operatorId, 'reason' => $reason];
    }
}
