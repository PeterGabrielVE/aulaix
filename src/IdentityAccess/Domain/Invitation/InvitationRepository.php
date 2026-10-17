<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Invitation;

use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\Shared\Domain\InstitutionId;

/**
 * Invitations are written only in the active institution, but read from the
 * central domain too (ADR 0006 C): every lookup names its institution.
 */
interface InvitationRepository
{
    public function byId(InstitutionId $institutionId, int $id): ?Invitation;

    public function pendingFor(InstitutionId $institutionId, EmailAddress $email): ?Invitation;

    /**
     * Persists the invitation; returns it with its id.
     */
    public function save(Invitation $invitation): Invitation;
}
