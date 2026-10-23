<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

/**
 * Facts about the signed-in account that only the central domain can see
 * and the institution's session keeps (SPEC-013 NAV-R02.5, R02.6).
 */
interface MembershipSession
{
    public function rememberActiveInstitutions(int $count): void;

    /**
     * Null when the session did not come through a handoff.
     */
    public function activeInstitutions(): ?int;
}
