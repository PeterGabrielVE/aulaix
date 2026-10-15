<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Role;

use AulaX\IdentityAccess\Domain\Account\AccountId;

/**
 * The roles of the active institution and who holds them. Every institution
 * has its own copy of the base roles (IAM-R11.1), so all of this is read and
 * written for the active institution only (IDG-R02.1).
 */
interface RoleCatalog
{
    /**
     * The roles that exist in the active institution.
     */
    public function available(): RoleSet;

    /**
     * The active institution's roles that carry the permission.
     */
    public function withPermission(Permission $permission): RoleSet;

    /**
     * The roles the account holds in the active institution.
     */
    public function rolesOf(AccountId $account): RoleSet;

    /**
     * Replace the roles the account holds in the active institution.
     */
    public function assign(AccountId $account, RoleSet $roles): void;

    /**
     * How many members with an active membership hold the role.
     */
    public function activeHoldersOf(BaseRole $role): int;
}
