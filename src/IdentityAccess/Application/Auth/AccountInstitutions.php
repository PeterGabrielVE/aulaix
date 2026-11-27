<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;

/**
 * Read side of the central domain: where a person can go. Only usable with
 * no institution active (ADR 0006 C).
 */
interface AccountInstitutions
{
    /**
     * Active institutions where the account has an active membership, by name.
     *
     * @return list<InstitutionChoice>
     */
    public function activeFor(AccountId $account): array;

    /**
     * Invitations to the email still pending and in time, newest first.
     *
     * @return list<PendingInvitationView>
     */
    public function pendingInvitationsFor(EmailAddress $email): array;
}
