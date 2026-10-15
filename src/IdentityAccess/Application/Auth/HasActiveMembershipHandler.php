<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Membership\MembershipRepository;

/**
 * Whether an account may work in the active institution right now: the
 * account is active and so is its membership there (IDG-R03.8). Checked on
 * login and on every request, so a suspension takes effect immediately.
 */
final readonly class HasActiveMembershipHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private MembershipRepository $memberships,
    ) {}

    public function __invoke(int $accountId): bool
    {
        $id = AccountId::fromInt($accountId);

        return (bool) $this->accounts->byId($id)?->isActive()
            && (bool) $this->memberships->ofAccount($id)?->isActive();
    }
}
