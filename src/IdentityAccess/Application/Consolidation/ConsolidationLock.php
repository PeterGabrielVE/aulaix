<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

use AulaX\IdentityAccess\Domain\Account\EmailAddress;

/**
 * Transaction-scoped advisory lock keyed by lowercased email address
 * (ADR 0014, SPEC-017 RSP-R04): serializes every consolidation and
 * kept-separate resolution on the same email.
 */
interface ConsolidationLock
{
    public function lockEmail(EmailAddress $email): void;
}
