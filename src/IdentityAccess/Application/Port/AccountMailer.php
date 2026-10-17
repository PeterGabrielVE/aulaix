<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;

/**
 * The emails about an account, sent to its own address unless said otherwise.
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

    /**
     * The verification link, sent to the new address (IDG-R05.2).
     */
    public function emailChangeVerification(Account $account, EmailAddress $newEmail, #[\SensitiveParameter] string $token): void;

    /**
     * Instead of a verification link, to an address that already has an
     * account: its owner learns someone tried; the requester learns nothing
     * (IDG-R05.4).
     */
    public function emailAlreadyInUse(EmailAddress $email): void;

    /**
     * To the previous address, once the change is verified (IDG-R05.3).
     */
    public function emailChanged(Account $before): void;

    /**
     * IDG-R08.5: the date it was asked, the date it closes, and how to cancel.
     */
    public function deletionScheduled(Account $account): void;

    /**
     * IDG-R08.10: to the owner and to support, why the closing waits.
     *
     * @param  list<string>  $reasons
     */
    public function deletionPostponed(Account $account, array $reasons): void;
}
