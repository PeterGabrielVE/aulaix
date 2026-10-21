<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Infrastructure;

use AulaX\Tenancy\Application\Port\InstitutionPermissions;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Asks Laravel's gate, which knows the permissions of the signed-in person
 * in the open institution (its permission team).
 */
final readonly class GateInstitutionPermissions implements InstitutionPermissions
{
    /**
     * IdentityAccess's Permission::ManageInstitution; its domain is not
     * reachable from here (A-06).
     */
    private const string MANAGE_INSTITUTION = 'gestionar-plantel';

    public function __construct(private Gate $gate) {}

    public function canManage(): bool
    {
        return $this->gate->allows(self::MANAGE_INSTITUTION);
    }
}
