<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure;

use AulaX\IdentityAccess\Application\Members\GrantRoleHandler;
use AulaX\IdentityAccess\Application\Members\InviteMemberCommand;
use AulaX\IdentityAccess\Application\Members\InviteMemberHandler;
use AulaX\IdentityAccess\Application\Members\MemberDirectory;
use AulaX\Students\Application\Port\GuardianInvitations;

/**
 * Through IdentityAccess's public use cases (A-06): its member directory,
 * its role grants and its invitations, with their own rules, audit and
 * email.
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
        private GrantRoleHandler $grantRole,
    ) {}

    public function memberWithEmail(string $email): ?int
    {
        return $this->members->activeMemberByEmail($email);
    }

    public function grantGuardianRole(int $actorId, int $memberId): void
    {
        ($this->grantRole)($actorId, $memberId, self::GUARDIAN_ROLE);
    }

    public function invite(int $actorId, string $email, string $name): int
    {
        return ($this->inviteMember)(new InviteMemberCommand($actorId, $email, $name, [self::GUARDIAN_ROLE]));
    }
}
