<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * Password reset tokens: one per account, kept hashed, valid for 60 minutes
 * and single-use (IAM-R05.7, R05.8, IDG-NF04).
 */
interface PasswordResetTokens
{
    /**
     * A new token, replacing any earlier one of the account.
     */
    public function create(AccountId $account): string;

    public function isValid(AccountId $account, #[\SensitiveParameter] string $token): bool;

    /**
     * Whether one was issued too recently to send another (IAM-R05.4).
     */
    public function recentlyCreated(AccountId $account): bool;

    public function delete(AccountId $account): void;
}
