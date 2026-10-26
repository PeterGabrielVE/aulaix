<?php

namespace Tests\Fixtures\Platform;

use AulaX\Audit\Application\AuditContext;
use AulaX\Audit\Application\AuditOrigin;

/**
 * In-memory stand-in for the Platform port of the same role (SPEC-014 tests).
 */
final class FakeAuditContext implements AuditContext
{
    public ?int $platformOperator = null;

    public function actorId(): ?int
    {
        return null;
    }

    public function origin(): AuditOrigin
    {
        return $this->platformOperator === null ? AuditOrigin::system() : AuditOrigin::platform('127.0.0.1', $this->platformOperator);
    }

    public function actAsConsoleOperator(string $operator): void {}

    public function actAsPlatformOperator(int $operatorId): void
    {
        $this->platformOperator = $operatorId;
    }
}
