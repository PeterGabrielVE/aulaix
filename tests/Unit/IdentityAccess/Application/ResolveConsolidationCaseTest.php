<?php

use AulaX\IdentityAccess\Application\Consolidation\AccountConsolidator;
use AulaX\IdentityAccess\Application\Consolidation\CaseResolutionRefused;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationDecision;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationLock;
use AulaX\IdentityAccess\Application\Consolidation\ConsolidationOperators;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseCommand;
use AulaX\IdentityAccess\Application\Consolidation\ResolveConsolidationCaseHandler;
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
 * SPEC-017 RSP-R04.1 – R04.4, R07.2, R07.3: ResolveConsolidationCaseHandler
 * locks by email on dry run and resolution, re-reads under the lock, and
 * audits previews without PII.
 */
function buildConsolidationHarness(): object
{
    $transactions = new InlineTransactionManager;
    $audit = new RecordingAuditTrail($transactions);

    $accounts = new class implements AccountRepository
    {
        /** @var array<int, Account> */
        public array $stored = [];

        public function __construct()
        {
            foreach ([11 => 'Ana Pérez', 12 => 'Ana María Pérez'] as $id => $name) {
                $this->stored[$id] = Account::reconstitute(
                    AccountId::fromInt($id),
                    EmailAddress::fromString('ana@example.com'),
                    PersonName::fromString($name),
                    AccountState::Active,
                    ConsolidationState::Review,
                    new DateTimeImmutable('2026-01-01'),
                );
            }
        }

        public function byId(AccountId $id): ?Account
        {
            return $this->stored[$id->value] ?? null;
        }

        public function byEmail(EmailAddress $email): array
        {
            return array_values(array_filter($this->stored, fn (Account $a) => $a->email->equals($email)));
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
            $this->stored[$account->id->value] = $account;

            return $account;
        }

        public function delete(AccountId $id): void
        {
            unset($this->stored[$id->value]);
        }
    };

    $locks = new class implements ConsolidationLock
    {
        /** @var list<string> */
        public array $locked = [];

        /** @var ?callable(): void */
        public $onLock = null;

        public function lockEmail(EmailAddress $email): void
        {
            $this->locked[] = $email->value;

            if ($this->onLock !== null) {
                ($this->onLock)();
            }
        }
    };

    $operators = new class implements ConsolidationOperators
    {
        public function isAuthorized(string $operator): bool
        {
            return $operator === 'luis@aulax.ve';
        }
    };

    $merger = new class implements AccountMerger
    {
        public function moveMemberships(AccountId $from, AccountId $to): void {}
    };

    $sessions = new class implements SessionRevoker
    {
        public int $revocations = 0;

        public function revokeAll(AccountId $account): void
        {
            $this->revocations++;
        }

        public function revokeOthers(AccountId $account): void {}
    };

    $mailer = new class implements AccountMailer
    {
        public int $consolidated = 0;

        public function passwordResetLink(Account $account, #[SensitiveParameter] string $token): void {}

        public function passwordChanged(Account $account): void {}

        public function consolidationGuidance(Account $account): void {}

        public function consolidationCode(Account $account, #[SensitiveParameter] string $code): void {}

        public function accountsConsolidated(Account $account): void
        {
            $this->consolidated++;
        }

        public function emailChangeVerification(Account $account, EmailAddress $newEmail, #[SensitiveParameter] string $token): void {}

        public function emailAlreadyInUse(EmailAddress $email): void {}

        public function emailChanged(Account $before): void {}

        public function deletionScheduled(Account $account): void {}

        public function deletionPostponed(Account $account, array $reasons): void {}
    };

    $consolidator = new AccountConsolidator($accounts, $locks, $merger, $sessions, $mailer, $audit, $transactions);
    $handler = new ResolveConsolidationCaseHandler($operators, $accounts, $locks, $consolidator, $audit, $transactions);

    return (object) compact('accounts', 'locks', 'sessions', 'mailer', 'audit', 'handler');
}

it('records a dry-run preview in the platform log with only decision and account ids (RSP-R07.2, R07.3)', function () {
    $h = buildConsolidationHarness();

    $plan = ($h->handler)(new ResolveConsolidationCaseCommand(
        'luis@aulax.ve', [12, 11], ConsolidationDecision::Consolidate, 'Cédula verificada', 'TICKET-1', dryRun: true,
    ));

    $serialized = json_encode($h->audit->changesOf(0));

    expect($plan->applied)->toBeFalse()
        ->and($h->locks->locked)->toBe(['ana@example.com'])
        ->and($h->audit->types())->toBe(['identity.consolidation.previewed'])
        ->and($h->audit->changesOf(0))->toBe([
            'decision' => ['from' => null, 'to' => 'consolidate'],
            'accounts' => ['from' => null, 'to' => [11, 12]],
        ])
        ->and($serialized)->not->toContain('ana@example.com')
        ->and($serialized)->not->toContain('Ana Pérez')
        ->and($h->accounts->stored)->toHaveCount(2);
});

it('refuses consolidation without email or session revocation if accounts became separated while waiting for the lock', function () {
    $h = buildConsolidationHarness();
    $h->locks->onLock = function () use ($h) {
        foreach ($h->accounts->stored as $id => $account) {
            $h->accounts->stored[$id] = $account->keptSeparate();
        }
    };

    expect(fn () => ($h->handler)(new ResolveConsolidationCaseCommand(
        'luis@aulax.ve', [11, 12], ConsolidationDecision::Consolidate, 'Verificada', 'TICKET-1', dryRun: false,
    )))->toThrow(CaseResolutionRefused::class);

    expect($h->accounts->stored)->toHaveCount(2)
        ->and($h->sessions->revocations)->toBe(0)
        ->and($h->mailer->consolidated)->toBe(0)
        ->and($h->audit->types())->toBe(['identity.consolidation.refused']);
});
