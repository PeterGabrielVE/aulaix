<?php

declare(strict_types=1);

namespace AulaX\Platform\Application\Port;

/**
 * The application log, for what the audit log must not say: why a login
 * failed (V5). Ids only, never emails or codes (S-07).
 */
interface SecurityLog
{
    public function loginFailed(?int $operatorId, string $reason): void;
}
