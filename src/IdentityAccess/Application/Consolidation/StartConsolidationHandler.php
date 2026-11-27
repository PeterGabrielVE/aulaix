<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Application\Port\ConsolidationCodes;
use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;

/**
 * Emails a one-time code to the owner of historical accounts that may be
 * merged (IDG-R09.4). Accounts marked for review never get one (IDG-R11.8).
 */
final readonly class StartConsolidationHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private ConsolidationCodes $codes,
        private AccountMailer $mailer,
    ) {}

    /**
     * @throws ConsolidationRejected
     */
    public function __invoke(int $accountId): void
    {
        $account = self::consolidatable($this->accounts->byId(AccountId::fromInt($accountId)));

        $this->mailer->consolidationCode($account, $this->codes->issue($account->id));
    }

    /**
     * @throws ConsolidationRejected
     */
    public static function consolidatable(?Account $account): Account
    {
        if ($account === null || $account->consolidation !== ConsolidationState::Pending) {
            throw ConsolidationRejected::notAvailable();
        }

        return $account;
    }
}
