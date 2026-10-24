<?php

declare(strict_types=1);

namespace AulaX\Workspace\Infrastructure;

use AulaX\IdentityAccess\Application\Members\InvitationView;
use AulaX\IdentityAccess\Application\Members\MemberDirectory;
use AulaX\Shared\Domain\Clock;
use AulaX\Workspace\Application\Port\StaffSource;

/**
 * Through IdentityAccess's member directory (A-06), the one the users
 * screen reads.
 */
final readonly class IdentityAccessStaffSource implements StaffSource
{
    /**
     * IdentityAccess's BaseRole cases, by priority; its domain is not
     * reachable from here (A-06).
     */
    private const array BASE_ROLES = ['Administrador', 'Director', 'Coordinador', 'Docente', 'Representante', 'Estudiante'];

    public function __construct(
        private MemberDirectory $members,
        private Clock $clock,
    ) {}

    public function membersByRole(): array
    {
        $counts = [];

        foreach (self::BASE_ROLES as $role) {
            $counts[$role] = count($this->members->activeMembersWithRole($role));
        }

        return $counts;
    }

    public function invitations(): array
    {
        $now = $this->clock->now();
        $invitations = $this->members->pendingInvitations(null);
        $expired = count(array_filter($invitations, fn (InvitationView $invitation) => $invitation->expiresAt < $now));

        return ['pending' => count($invitations) - $expired, 'expired' => $expired];
    }
}
