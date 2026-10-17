<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\NotFound;

/**
 * The owner changes their mind within the grace period (IDG-R08.9): the
 * account is active again, and only the memberships suspended for the
 * deletion come back; those an institution suspended stay so.
 */
final readonly class CancelDeletionHandler
{
    public function __construct(
        private AccountRepository $accounts,
        private AccountMemberships $memberships,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(int $accountId): void
    {
        $id = AccountId::fromInt($accountId);
        $account = $this->accounts->byId($id) ?? throw new NotFound('La cuenta no existe.');
        $active = $account->cancelDeletion();

        $this->transactions->run(function () use ($id, $active) {
            $this->accounts->save($active);
            $this->memberships->reactivateAfterCancelledDeletion($id);

            $this->audit->recordForPlatform(AuditEvent::of('identity.account.deletion_cancelled', AuditSubject::of('account', $id->value)));
        });
    }
}
