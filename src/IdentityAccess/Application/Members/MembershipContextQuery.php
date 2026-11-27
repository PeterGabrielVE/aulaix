<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Members;

use AulaX\IdentityAccess\Application\Port\MembershipSession;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Role\RoleCatalog;

/**
 * The public API for other modules (SPEC-013): an account's own roles in the
 * open institution and how many institutions it may switch between. Only
 * ever asked about the signed-in account, so it checks no permission.
 *
 * With an institution open, RLS hides the other memberships (ADR 0006): the
 * count is the one the handoff brought from the central domain.
 */
final readonly class MembershipContextQuery
{
    public function __construct(
        private RoleCatalog $roles,
        private MembershipSession $session,
    ) {}

    public function __invoke(int $accountId): MembershipContext
    {
        $account = AccountId::fromInt($accountId);
        $roles = $this->roles->rolesOf($account);

        return new MembershipContext(
            $roles->inPriorityOrder(),
            $roles->primaryRole()?->value,
            $this->session->activeInstitutions() ?? 1,
        );
    }
}
