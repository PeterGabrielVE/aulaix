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
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\HashedPassword;
use AulaX\Shared\Application\TransactionManager;
use Closure;
use LogicException;

/**
 * Merges historical accounts sharing an email into the oldest one (design:
 * Consolidación, ADR 0014, SPEC-017 RSP-R04): inside one transaction it takes
 * ConsolidationLock::lockEmail(), re-reads every account sharing the email,
 * runs the caller's group check, moves memberships and roles, deletes the
 * others, saves the survivor and records the platform log entry. Session
 * revocation and email notification happen strictly after commit (RSP-R04.4).
 *
 * No password is copied between accounts (IDG-R09.7): the survivor gets the
 * new one chosen by its owner, or keeps its own.
 */
final readonly class AccountConsolidator
{
    public function __construct(
        private AccountRepository $accounts,
        private ConsolidationLock $locks,
        private AccountMerger $merger,
        private SessionRevoker $sessions,
        private AccountMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @param  Closure(list<Account>): list<Account>  $check  Validates the re-read group under the lock and returns the accounts to merge.
     * @param  list<FieldChange>  $context  Extra facts for the log, e.g. support's decision and reason.
     * @return Account The survivor.
     */
    public function consolidate(EmailAddress $email, Closure $check, ?HashedPassword $newPassword, array $context = []): Account
    {
        /** @var array{0: Account, 1: list<Account>} $committed */
        $committed = $this->transactions->run(function () use ($email, $check, $newPassword, $context) {
            $this->locks->lockEmail($email);
            $group = $check($this->accounts->byEmail($email));

            if (count($group) < 2) {
                throw new LogicException('A consolidation needs at least two accounts.');
            }

            usort($group, fn (Account $a, Account $b) => $a->id->value <=> $b->id->value);
            [$survivor, $others] = [$group[0], array_slice($group, 1)];

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

            return [$merged, $others];
        });

        [$survivor, $others] = $committed;

        foreach ($others as $other) {
            $this->sessions->revokeAll($other->id);
        }

        $this->mailer->accountsConsolidated($survivor);

        return $survivor;
    }
}
