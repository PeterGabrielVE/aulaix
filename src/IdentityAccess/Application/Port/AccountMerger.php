<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * Moves what an account holds in every institution (its memberships and
 * their roles) to another account, institution by institution, so the first
 * one can be deleted. Never touches either password (IDG-R09.7).
 */
interface AccountMerger
{
    public function moveMemberships(AccountId $from, AccountId $into): void;
}
