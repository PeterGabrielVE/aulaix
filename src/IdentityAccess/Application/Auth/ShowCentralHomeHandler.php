<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;
use AulaX\Shared\Domain\NotFound;

/**
 * What a logged-in person sees on the central domain: the institutions they
 * can open (IDG-R03.5), or a welcome with their pending invitations when
 * there are none (IDG-R03.6).
 */
final readonly class ShowCentralHomeHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private AccountInstitutions $institutions,
    ) {}

    public function __invoke(int $accountId): CentralHome
    {
        $id = AccountId::fromInt($accountId);
        $account = $this->accounts->byId($id) ?? throw new NotFound('La cuenta no existe.');

        return new CentralHome(
            $account->name->value,
            $this->institutions->activeFor($id),
            $this->institutions->pendingInvitationsFor($account->email),
            $account->consolidation === ConsolidationState::Pending,
        );
    }
}
