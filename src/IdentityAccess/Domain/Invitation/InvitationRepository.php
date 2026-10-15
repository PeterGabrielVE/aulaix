<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Invitation;

use AulaX\IdentityAccess\Domain\Account\EmailAddress;

/**
 * Invitations of the active institution (Row Level Security limits every
 * read and write to it).
 */
interface InvitationRepository
{
    public function byId(int $id): ?Invitation;

    public function pendingFor(EmailAddress $email): ?Invitation;

    /**
     * Persists the invitation; returns it with its id.
     */
    public function save(Invitation $invitation): Invitation;
}
