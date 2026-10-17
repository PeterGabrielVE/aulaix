<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Shared\Domain\InstitutionId;

/**
 * Tokens that open an institution's subdomain for an account already
 * authenticated on the central domain, without asking for the password again
 * (IDG-R03.7). Single-use, valid for 60 seconds, bound to one account and
 * one institution (IDG-NF03).
 */
interface HandoffTokens
{
    public function issue(AccountId $account, InstitutionId $institution): string;

    /**
     * The account the token was issued for, or null when it is forged,
     * expired, already used or meant for another institution.
     */
    public function consume(#[\SensitiveParameter] string $token, InstitutionId $institution): ?AccountId;
}
