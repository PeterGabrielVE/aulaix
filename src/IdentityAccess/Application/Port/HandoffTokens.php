<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Application\Auth\HandoffGrant;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Shared\Domain\InstitutionId;

/**
 * Tokens that open an institution's subdomain for an account already
 * authenticated on the central domain, without asking for the password again
 * (IDG-R03.7). Single-use, valid for 60 seconds, bound to one account and
 * one institution (IDG-NF03), and to the browser that asked for it
 * (SPEC-015 RLT-R04).
 */
interface HandoffTokens
{
    /**
     * @param  int  $activeInstitutions  Where the account is active, counted on the central domain.
     * @param  string  $browser  Hash of the secret of the browser that asked for it (SPEC-015 RLT-R04).
     */
    public function issue(AccountId $account, InstitutionId $institution, int $activeInstitutions = 1, string $browser = ''): string;

    /**
     * The account the token was issued for, with its count of institutions,
     * or null when it is forged, expired, already used, meant for another
     * institution or opened in another browser. A token opened in another
     * browser is not burnt: its owner can still use it.
     *
     * @param  string  $browser  Hash of the secret of the browser that opened it.
     */
    public function consume(#[\SensitiveParameter] string $token, InstitutionId $institution, string $browser = ''): ?HandoffGrant;
}
