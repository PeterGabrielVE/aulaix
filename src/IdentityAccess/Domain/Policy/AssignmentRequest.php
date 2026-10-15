<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Policy;

use AulaX\IdentityAccess\Domain\Role\RoleSet;

/**
 * A manager giving a member roles (and, when editing, a status).
 */
final readonly class AssignmentRequest
{
    /**
     * @param  ?RoleSet  $targetRoles  The member's current roles; null when inviting someone new.
     */
    public function __construct(
        public RoleSet $actorRoles,
        public ?RoleSet $targetRoles,
        public RoleSet $requestedRoles,
        public bool $editingSelf,
        public bool $requestedActive = true,
    ) {}
}
