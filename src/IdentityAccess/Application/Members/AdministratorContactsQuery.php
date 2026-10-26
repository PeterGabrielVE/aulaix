<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\IdentityAccess\Domain\Role\BaseRole;
use AulaX\Shared\Application\PlatformReadScope;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * For the platform (SPEC-014 PLT-R05.3, V2): who to tell when the open
 * institution is deactivated or reactivated. Only inside the platform's
 * bounded read, and never shown on a screen.
 */
final readonly class AdministratorContactsQuery
{
    public function __construct(private MemberDirectory $members) {}

    /**
     * @return list<array{name: string, email: string}>
     *
     * @throws AuthorizationDenied
     */
    public function __invoke(): array
    {
        PlatformReadScope::ensureActive();

        $contacts = [];

        foreach ($this->members->activeMembersWithRole(BaseRole::Administrador->value) as $administrador) {
            $member = $this->members->member($administrador['id']);

            if ($member !== null) {
                $contacts[] = ['name' => $member->name, 'email' => $member->email];
            }
        }

        return $contacts;
    }
}
