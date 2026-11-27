<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\IdentityAccess\Domain\Policy\RoleAssignmentPolicy;
use AulaX\IdentityAccess\Domain\Role\Permission;
use AulaX\Shared\Domain\NotFound;

/**
 * An Administrador grants or withdraws, to one member of the active
 * institution, the permission to manage enrollments (SPEC-010 MAT-R11.3),
 * e.g. to the secretary. It is the person's, not a role's, and only applies
 * here.
 */
final readonly class SetEnrollmentManagementHandler
{
    public function __construct(
        private DirectPermissionGrant $grant,
        private RoleAssignmentPolicy $policy,
    ) {}

    /**
     * @throws NotFound When the account is not a member of the active institution.
     */
    public function __invoke(int $actorId, int $memberId, bool $allowed): void
    {
        ($this->grant)($actorId, $memberId, Permission::ManageEnrollments, $allowed, $this->policy->enrollmentManagementGranting(...));
    }
}
