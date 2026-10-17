<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

use AulaX\IdentityAccess\Application\Port\Authenticator;
use AulaX\IdentityAccess\Application\Port\ConsolidationCodes;
use AulaX\IdentityAccess\Application\Port\PasswordHasher;
use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;

/**
 * With the code from their email, the owner merges every historical account
 * sharing it into one, with a single new password (IDG-R09.4). They stay
 * logged in, as the account that survives.
 */
final readonly class CompleteConsolidationHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private ConsolidationCodes $codes,
        private PasswordHasher $hasher,
        private AccountConsolidator $consolidator,
        private Authenticator $authenticator,
    ) {}

    /**
     * @throws ConsolidationRejected
     */
    public function __invoke(CompleteConsolidationCommand $command): void
    {
        $account = StartConsolidationHandler::consolidatable($this->accounts->byId(AccountId::fromInt($command->accountId)));

        if (! $this->codes->verify($account->id, $command->code)) {
            throw ConsolidationRejected::invalidCode();
        }

        $group = $this->accounts->byEmail($account->email);

        // The whole group must still be waiting for its owner (IDG-R11.8).
        foreach ($group as $member) {
            if ($member->consolidation !== ConsolidationState::Pending) {
                throw ConsolidationRejected::notAvailable();
            }
        }

        $survivor = count($group) > 1
            ? $this->consolidator->consolidate($group, $this->hasher->hash($command->newPassword))
            : $this->accounts->save($account->consolidated()->changePassword($this->hasher->hash($command->newPassword)));

        $this->authenticator->logIn($survivor->id);
    }
}
