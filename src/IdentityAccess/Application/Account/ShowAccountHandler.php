<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\AccountState;
use AulaX\Shared\Domain\NotFound;

/**
 * The owner's own account, as their account page shows it.
 */
final readonly class ShowAccountHandler
{
    public function __construct(private AccountRepository $accounts) {}

    public function __invoke(int $accountId): AccountView
    {
        $account = $this->accounts->byId(AccountId::fromInt($accountId)) ?? throw new NotFound('La cuenta no existe.');

        return new AccountView(
            $account->name->value,
            $account->email->value,
            $account->pendingEmailChange?->email->value,
            $account->state === AccountState::PendingDeletion,
            $account->deletionDueAt,
        );
    }
}
