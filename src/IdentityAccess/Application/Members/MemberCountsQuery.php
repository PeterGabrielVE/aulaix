<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\Shared\Application\PlatformReadScope;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * For the platform (SPEC-014 PLT-R04.3, V2): how many active members the
 * open institution has with each base role. Numbers only, and only inside
 * the platform's bounded read.
 */
final readonly class MemberCountsQuery
{
    public function __construct(private MemberDirectory $members) {}

    /**
     * @return array<string, int> Base role => active members, by priority.
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(): array
    {
        PlatformReadScope::ensureActive();

        $counts = [];

        foreach (BaseRole::cases() as $role) {
            $counts[$role->value] = count($this->members->activeMembersWithRole($role->value));
        }

        return $counts;
    }
}
