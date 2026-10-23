<?php

declare(strict_types=1);

namespace AulaX\Workspace\Domain;

/**
 * What a person can do in the open institution: the permissions their roles
 * give, their roles, the highest-priority one (IAM-R12.2) and whether they
 * are linked to a guardian (EST-R08.2). Names are IdentityAccess's, as text:
 * its domain is not reachable from here (A-06).
 */
final readonly class Capabilities
{
    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     */
    public function __construct(
        public array $permissions,
        public array $roles,
        public ?string $primaryRole,
        public bool $isGuardian = false,
    ) {}

    public function has(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
