<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * What a valid handoff token carries into the institution: the account, and
 * how many institutions it was active in when the central domain could see
 * them all (SPEC-013 NAV-R02.5; RLS hides the others afterwards, ADR 0006).
 */
final readonly class HandoffGrant
{
    public function __construct(
        public AccountId $account,
        public int $activeInstitutions,
    ) {}
}
