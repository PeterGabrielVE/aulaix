<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Application\Port\AccountMerger;
use AulaX\IdentityAccess\Application\Port\SessionRevoker;
use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\HashedPassword;
use AulaX\Shared\Application\TransactionManager;
use LogicException;

/**
 * Merges historical accounts sharing an email into the oldest one (design:
 * Consolidación): their memberships and roles move to it, the others are
 * deleted with their sessions, and the survivor becomes a regular account.
 * All of it in one transaction, recorded in the platform log (IDG-R11.6,
 * R11.7). The owner's flow and the support command both go through here
 * (IDG-NF07).
 *
 * No password is copied between accounts (IDG-R09.7): the survivor gets the
 * new one chosen by its owner, or keeps its own.
 */
final readonly class AccountConsolidator
{
    public function __construct(
        private AccountRepository $accounts,
        private AccountMerger $merger,
        private SessionRevoker $sessions,
        private AccountMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  list<Account>  $group  Every account sharing the email, at least two.
     * @param  list<FieldChange>  $context  Extra facts for the log, e.g. support's decision and reason.
     * @return Account The survivor.
     */
    public function consolidate(array $group, ?HashedPassword $newPassword, array $context = []): Account
    {
        if (count($group) < 2) {
            throw new LogicException('A consolidation needs at least two accounts.');
        }

        usort($group, fn (Account $a, Account $b) => $a->id->value <=> $b->id->value);
        [$survivor, $others] = [$group[0], array_slice($group, 1)];

        $survivor = $this->transactions->run(function () use ($survivor, $others, $newPassword, $context) {
            foreach ($others as $other) {
                $this->merger->moveMemberships($other->id, $survivor->id);
                $this->accounts->delete($other->id);
            }

            $merged = $survivor->consolidated();
            $merged = $this->accounts->save($newPassword === null ? $merged : $merged->changePassword($newPassword));

            $this->audit->recordForPlatform(AuditEvent::of(
                'identity.account.consolidated',
                AuditSubject::of('account', $merged->id->value),
                [FieldChange::of('merged_accounts', array_map(fn (Account $other) => $other->id->value, $others), []), ...$context],
            ));

            return $merged;
        });

        foreach ($others as $other) {
            $this->sessions->revokeAll($other->id);
        }

        $this->mailer->accountsConsolidated($survivor);

        return $survivor;
    }
}
