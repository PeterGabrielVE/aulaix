<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Membership;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Shared\Domain\InstitutionId;

/**
 * Memberships are written only in the active institution, but read from the
 * central domain too (ADR 0006 C): every lookup names its institution.
 */
interface MembershipRepository
{
    public function of(AccountId $accountId, InstitutionId $institutionId): ?Membership;

    /**
     * The account's memberships in every institution (central domain only).
     *
     * @return list<Membership>
     */
    public function allOf(AccountId $accountId): array;

    /**
     * Persists the membership; returns it with its id.
     */
    public function save(Membership $membership): Membership;
}
