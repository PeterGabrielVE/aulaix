<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

use AulaX\IdentityAccess\Application\Auth\AccountInstitutions;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;

/**
 * What the owner is about to merge: the institutions of every account
 * sharing their email, by name.
 */
final readonly class ShowConsolidationHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private AccountInstitutions $institutions,
    ) {}

    /**
     * @return list<string> Institution names.
     *
     * @throws ConsolidationRejected
     */
    public function __invoke(int $accountId): array
    {
        $account = StartConsolidationHandler::consolidatable($this->accounts->byId(AccountId::fromInt($accountId)));

        $names = [];

        foreach ($this->accounts->byEmail($account->email) as $member) {
            foreach ($this->institutions->activeFor($member->id) as $institution) {
                $names[] = $institution->name;
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }
}
