<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\Account;

/**
 * The emails about an account, sent to its own address.
 */
interface AccountMailer
{
    public function passwordResetLink(Account $account, #[\SensitiveParameter] string $token): void;

    /**
     * After a password change or reset, so a change the owner did not make
     * does not go unnoticed (IDG-R06.1).
     */
    public function passwordChanged(Account $account): void;

    /**
     * Instead of a reset link, for an email shared by historical accounts
     * still waiting to be consolidated: there is no single password to reset.
     */
    public function consolidationGuidance(Account $account): void;

    /**
     * The one-time code that proves they receive mail here (IDG-R09.4).
     */
    public function consolidationCode(Account $account, #[\SensitiveParameter] string $code): void;

    public function accountsConsolidated(Account $account): void;
}
