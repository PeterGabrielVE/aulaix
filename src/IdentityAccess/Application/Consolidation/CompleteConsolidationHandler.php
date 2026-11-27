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
use AulaX\Shared\Application\TransactionManager;

/**
 * With the code from their email, the owner merges every historical account
 * sharing it into one, with a single new password (IDG-R09.4). They stay
 * logged in, as the account that survives. Serialized under ConsolidationLock
 * (ADR 0014, SPEC-017 RSP-R04).
 */
final readonly class CompleteConsolidationHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private ConsolidationLock $locks,
        private ConsolidationCodes $codes,
        private PasswordHasher $hasher,
        private AccountConsolidator $consolidator,
        private Authenticator $authenticator,
        private TransactionManager $transactions,
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

        $hashedPassword = $this->hasher->hash($command->newPassword);
        $initialGroup = $this->accounts->byEmail($account->email);

        if (count($initialGroup) > 1) {
            $survivor = $this->consolidator->consolidate(
                $account->email,
                function (array $group): array {
                    if (count($group) < 2) {
                        throw ConsolidationRejected::notAvailable();
                    }

                    // The whole group must still be waiting for its owner (IDG-R11.8).
                    foreach ($group as $member) {
                        if ($member->consolidation !== ConsolidationState::Pending) {
                            throw ConsolidationRejected::notAvailable();
                        }
                    }

                    return $group;
                },
                $hashedPassword,
            );
        } else {
            /** @var Account $survivor */
            $survivor = $this->transactions->run(function () use ($account, $hashedPassword) {
                $this->locks->lockEmail($account->email);
                $fresh = $this->accounts->byEmail($account->email);

                if (count($fresh) !== 1 || $fresh[0]->consolidation !== ConsolidationState::Pending) {
                    throw ConsolidationRejected::notAvailable();
                }

                return $this->accounts->save($fresh[0]->consolidated()->changePassword($hashedPassword));
            });
        }

        $this->authenticator->logIn($survivor->id);
    }
}
