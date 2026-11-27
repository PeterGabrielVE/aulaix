<?php

declare(strict_types=1);

namespace AulaX\Workspace\Application\Port;

/**
 * The institution's staff and invitations, from IdentityAccess.
 */
interface StaffSource
{
    /**
     * @return array<string, int> Active members by base role, in priority order.
     */
    public function membersByRole(): array;

    /**
     * @return array{pending: int, expired: int} Invitations not accepted, still valid or expired.
     */
    public function invitations(): array;
}
