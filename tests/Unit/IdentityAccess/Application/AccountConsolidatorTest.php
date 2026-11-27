<?php

use AulaX\IdentityAccess\Application\Consolidation\AccountConsolidator;
use AulaX\IdentityAccess\Application\Consolidation\CaseResolutionRefused;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationLock;
use AulaX\IdentityAccess\Application\Port\AccountMailer;
use AulaX\IdentityAccess\Application\Port\AccountMerger;
use AulaX\IdentityAccess\Application\Port\SessionRevoker;
use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountRepository;
use AulaX\IdentityAccess\Domain\Account\AccountState;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-017 RSP-R04.1, R04.3, R04.4: AccountConsolidator locks the email,
 * re-reads the group, validates under the lock, and only revokes sessions or
 * sends mail after commit.
 */
function consolidationAccount(int $id, string $email, ?ConsolidationState $state = ConsolidationState::Review): Account
{
    return Account::reconstitute(
        AccountId::fromInt($id),
        EmailAddress::fromString($email),
        PersonName::fromString("Persona {$id}"),
        AccountState::Active,
        $state,
        new DateTimeImmutable('2026-01-01'),
    );
}

it('locks the email before re-reading and triggers no side effects when the check refuses', function () {
    $email = EmailAddress::fromString('ana@example.com');
    $events = [];

    $accounts = new class($email, $events) implements AccountRepository
    {
        /** @var array<int, Account> */
        public array $stored = [];

        public function __construct(EmailAddress $email, public array &$events)
        {
            $this->stored[10] = consolidationAccount(10, $email->value, ConsolidationState::Separated);
            $this->stored[20] = consolidationAccount(20, $email->value, ConsolidationState::Separated);
        }

        public function byId(AccountId $id): ?Account
        {
            return $this->stored[$id->value] ?? null;
        }

        public function byEmail(EmailAddress $email): array
        {
            $this->events[] = 'byEmail';

            return array_values($this->stored);
        }

        public function byPendingEmailToken(#[SensitiveParameter] string $token): ?Account
        {
            return null;
        }

        public function dueForClosing(DateTimeImmutable $now): array
        {
            return [];
        }

        public function save(Account $account): Account
        {
            $this->events[] = 'save';

            return $account;
        }

        public function delete(AccountId $id): void
        {
            $this->events[] = 'delete';
        }
    };

    $locks = new class($events) implements ConsolidationLock
    {
        public function __construct(public array &$events) {}

        public function lockEmail(EmailAddress $email): void
        {
            $this->events[] = "lock:{$email->value}";
        }
    };

    $merger = new class($events) implements AccountMerger
    {
        public function __construct(public array &$events) {}

        public function moveMemberships(AccountId $from, AccountId $to): void
        {
            $this->events[] = 'merge';
        }
    };

    $sessions = new class($events) implements SessionRevoker
    {
        public function __construct(public array &$events) {}

        public function revokeAll(AccountId $account): void
        {
            $this->events[] = 'revoke';
        }

        public function revokeOthers(AccountId $account): void {}
    };

    $mailer = new class($events) implements AccountMailer
    {
        public function __construct(public array &$events) {}

        public function passwordResetLink(Account $account, #[SensitiveParameter] string $token): void {}

        public function passwordChanged(Account $account): void {}

        public function consolidationGuidance(Account $account): void {}

        public function consolidationCode(Account $account, #[SensitiveParameter] string $code): void {}

        public function accountsConsolidated(Account $account): void
        {
            $this->events[] = 'mail';
        }

        public function emailChangeVerification(Account $account, EmailAddress $newEmail, #[SensitiveParameter] string $token): void {}

        public function emailAlreadyInUse(EmailAddress $email): void {}

        public function emailChanged(Account $before): void {}

        public function deletionScheduled(Account $account): void {}

        public function deletionPostponed(Account $account, array $reasons): void {}
    };

    $transactions = new InlineTransactionManager;
    $audit = new RecordingAuditTrail($transactions);

    $consolidator = new AccountConsolidator($accounts, $locks, $merger, $sessions, $mailer, $audit, $transactions);

    expect(fn () => $consolidator->consolidate($email, function (array $group): array {
        foreach ($group as $account) {
            if ($account->consolidation !== ConsolidationState::Review) {
                throw CaseResolutionRefused::notUnderReview();
            }
        }

        return $group;
    }, null))->toThrow(CaseResolutionRefused::class);

    expect($events)->toBe(['lock:ana@example.com', 'byEmail'])
        ->and($audit->types())->toBe([]);
});
