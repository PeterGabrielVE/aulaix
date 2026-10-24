<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Account;

use AulaX\Audit\Application\AuditEvent;
use AulaX\Audit\Application\AuditSubject;
use AulaX\Audit\Application\AuditTrail;
use AulaX\Audit\Application\FieldChange;
use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Application\Port\PasswordHasher;
use AulaX\IdentityAccess\Application\Port\SessionRevoker;
use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\Clock;

/**
 * The daily closing of accounts whose grace period ended (IDG-R08.10,
 * R08.11). The blockers are checked again: if one still holds, the closing
 * waits a week and the owner and support are told. Otherwise the account is
 * closed for good and its memberships end. Nothing is erased or anonymized
 * until the legal criteria exist (IDG-R08.12, R08.13, dependency D1).
 */
final readonly class CloseDueAccountsHandler
{
    private const POSTPONEMENT = '+7 days';

    public function __construct(
        private AccountRepository $accounts,
        private DeletionBlockers $blockers,
        private AccountMemberships $memberships,
        private PasswordHasher $hasher,
        private SessionRevoker $sessions,
        private AccountMailer $mailer,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private Clock $clock,
    ) {}

    /**
     * @return array{closed: int, postponed: int}
     */
    public function __invoke(): array
    {
        $now = $this->clock->now();
        $result = ['closed' => 0, 'postponed' => 0];

        foreach ($this->accounts->dueForClosing($now) as $account) {
            $reasons = $this->blockers->reasonsFor($account->id);

            $reasons === [] ? $this->close($account) : $this->postpone($account, $reasons, $now);
            $result[$reasons === [] ? 'closed' : 'postponed']++;
        }

        return $result;
    }

    /**
     * @param  list<string>  $reasons
     */
    private function postpone(Account $account, array $reasons, \DateTimeImmutable $now): void
    {
        $postponed = $account->postponeDeletion($now->modify(self::POSTPONEMENT));

        $this->transactions->run(function () use ($postponed, $reasons) {
            $this->accounts->save($postponed);
            $this->audit->recordForPlatform(AuditEvent::of(
                'identity.account.deletion_postponed',
                AuditSubject::of('account', $postponed->id->value),
                [FieldChange::of('blockers', null, count($reasons))],
            ));
        });

        $this->mailer->deletionPostponed($postponed, $reasons);
    }

    private function close(Account $account): void
    {
        // A password nobody knows: the credentials are gone for good.
        $closed = $account->close($this->hasher->hash(bin2hex(random_bytes(32))));

        $this->transactions->run(function () use ($closed) {
            $this->memberships->endForClosedAccount($closed->id);
            $this->accounts->save($closed);
            $this->audit->recordForPlatform(AuditEvent::of('identity.account.closed', AuditSubject::of('account', $closed->id->value)));
        });

        $this->sessions->revokeAll($closed->id);
    }
}
