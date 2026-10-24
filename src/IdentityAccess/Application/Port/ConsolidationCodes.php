<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * One-time codes that prove the owner of historical accounts receives mail
 * at their email (IDG-R09.4): six digits, kept hashed, valid for 15 minutes,
 * single-use and burnt after 5 wrong attempts (IDG-NF04). Asking for another
 * replaces the previous one.
 */
interface ConsolidationCodes
{
    public function issue(AccountId $account): string;

    public function verify(AccountId $account, #[\SensitiveParameter] string $code): bool;
}
