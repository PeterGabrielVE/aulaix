<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Membership;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * Memberships of the active institution (Row Level Security limits every
 * read and write to it).
 */
interface MembershipRepository
{
    public function ofAccount(AccountId $accountId): ?Membership;

    /**
     * Persists the membership; returns it with its id.
     */
    public function save(Membership $membership): Membership;
}
