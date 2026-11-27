<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;
use AulaX\Tenancy\Application\CurrentTenant;

/**
 * Whether an account may work in the active institution right now: the
 * account is active and so is its membership there (IDG-R03.8). Checked on
 * every request, so a suspension takes effect immediately. With no
 * institution active there is nothing to work in: false.
 */
final readonly class HasActiveMembershipHandler
{
    public function __construct(
        private CurrentTenant $tenant,
        private AccountRepository $accounts,
        private MembershipRepository $memberships,
    ) {}

    public function __invoke(int $accountId): bool
    {
        $institutionId = $this->tenant->institutionId();

        if ($institutionId === null) {
            return false;
        }

        $id = AccountId::fromInt($accountId);

        return (bool) $this->accounts->byId($id)?->isActive()
            && (bool) $this->memberships->of($id, $institutionId)?->isActive();
    }
}
