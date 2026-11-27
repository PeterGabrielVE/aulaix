<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Infrastructure;

use AulaX\AcademicStructure\Application\Port\StaffDirectory;
use AulaX\IdentityAccess\Application\Members\MemberDirectory;

/**
 * Through IdentityAccess's public member directory (A-06), under the Row
 * Level Security of the open institution's memberships.
 */
final readonly class IdentityAccessStaffDirectory implements StaffDirectory
{
    private const string UNKNOWN_PERSON = 'Persona sin acceso';

    public function __construct(private MemberDirectory $members) {}

    public function isActiveWithRole(int $accountId, string $role): bool
    {
        $member = $accountId > 0 ? $this->members->member($accountId) : null;

        return $member !== null && $member->active && in_array($role, $member->roles, true);
    }

    public function candidates(string $role): array
    {
        return $this->members->activeMembersWithRole($role);
    }

    public function describe(array $accountIds): array
    {
        $accountIds = array_values(array_unique($accountIds));
        $known = $this->members->accountNames($accountIds);
        $described = [];

        foreach ($accountIds as $id) {
            $described[$id] = $known[$id] ?? ['name' => self::UNKNOWN_PERSON, 'active' => false];
        }

        return $described;
    }
}
