<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure;

use AulaX\IdentityAccess\Application\Members\InviteMemberCommand;
use AulaX\IdentityAccess\Application\Members\InviteMemberHandler;
use AulaX\IdentityAccess\Application\Members\MemberDirectory;
use AulaX\Students\Application\Port\GuardianInvitations;

/**
 * Through IdentityAccess's public use cases (A-06): its member directory and
 * its invitations, with their own rules, audit and email.
 */
final readonly class IdentityAccessGuardianInvitations implements GuardianInvitations
{
    /**
     * IdentityAccess's BaseRole::Representante; its domain is not reachable
     * from here (A-06).
     */
    private const string GUARDIAN_ROLE = 'Representante';

    public function __construct(
        private MemberDirectory $members,
        private InviteMemberHandler $inviteMember,
    ) {}

    public function memberWithEmail(string $email): ?int
    {
        return $this->members->activeMemberByEmail($email);
    }

    public function invite(int $actorId, string $email, string $name): int
    {
        return ($this->inviteMember)(new InviteMemberCommand($actorId, $email, $name, [self::GUARDIAN_ROLE]));
    }
}
