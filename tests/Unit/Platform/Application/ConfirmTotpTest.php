<?php

use AulaX\Platform\Application\Access\CodeAttempt;
use AulaX\Platform\Application\Access\ConfirmTotpHandler;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Platform\CountingOperatorLock;
use Tests\Fixtures\Platform\FakeOperatorSession;
use Tests\Fixtures\Platform\FakeRecoveryCodeHasher;
use Tests\Fixtures\Platform\FakeTotpCodes;
use Tests\Fixtures\Platform\InMemoryOperatorRepository;
use Tests\Fixtures\Platform\RecordingOperatorMailer;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-017 RSP-R01.4, R03.2, R03.5 – R03.8: TOTP confirmation inside an open
 * session shares the same locked attempt counter and lockout rules as login.
 */
beforeEach(function () {
    $this->operators = new InMemoryOperatorRepository;
    $this->locks = new CountingOperatorLock;
    $this->totp = new FakeTotpCodes;
    $this->hasher = new FakeRecoveryCodeHasher;
    $this->mailer = new RecordingOperatorMailer;
    $this->session = new FakeOperatorSession;
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->clock = new FixedClock(new DateTimeImmutable('2026-11-27 10:00:00'));

    $boss = Operator::invite('Jefa', OperatorEmail::fromString('jefa@aulax.ve'), OperatorRole::SuperAdmin);
    $boss->activate('hash:jefa', 'SECRET-JEFA', step: 1);
    $this->boss = $this->operators->save($boss);

    $operator = Operator::invite('Ana', OperatorEmail::fromString('ana@aulax.ve'), OperatorRole::Support);
    $operator->activate('hash:ana', 'SECRET-ANA', step: 100);
    $this->ana = $this->operators->save($operator);
    $this->session->start($this->operators->byId($this->ana), $this->clock->now->modify('-15 minutes'));
    $this->totp->codes['SECRET-ANA'] = ['123456' => 101, '111111' => 100];
});

function confirmTotp(string $code, string $ip = '198.51.100.1'): void
{
    $test = test();
    $codeAttempt = new CodeAttempt(
        $test->operators, $test->locks, $test->totp, $test->hasher, $test->mailer,
        $test->audit, $test->transactions, $test->clock,
    );

    (new ConfirmTotpHandler($test->operators, $test->session, $codeAttempt, $test->clock))($code, $ip);
}

it('locks before re-reading, confirms a fresh TOTP and clears previous failures', function () {
    for ($i = 1; $i <= 3; $i++) {
        expect(fn () => confirmTotp('999999', "198.51.100.{$i}"))->toThrow(BusinessRuleViolation::class, 'El código no es correcto.');
    }

    confirmTotp('123456');

    expect($this->locks->operatorLocks)->toHaveCount(4)
        ->and($this->session->totpConfirmedAt())->toEqual($this->clock->now)
        ->and($this->operators->byId($this->ana)->codeFailures)->toBe([]);
});

it('rejects a reused TOTP step and counts it as a code failure', function () {
    expect(fn () => confirmTotp('111111'))->toThrow(BusinessRuleViolation::class, 'El código no es correcto.')
        ->and($this->operators->byId($this->ana)->codeFailures)->toHaveCount(1);
});

it('ends the current session immediately, audits and emails on the tenth failed confirmation', function () {
    for ($i = 1; $i <= 10; $i++) {
        expect(fn () => confirmTotp('999999', "203.0.113.{$i}"))->toThrow(BusinessRuleViolation::class);
    }

    expect($this->session->current())->toBeNull()
        ->and($this->operators->byId($this->ana)->isBlockedAt($this->clock->now))->toBeTrue()
        ->and($this->audit->types())->toBe(['platform.operator.blocked'])
        ->and($this->mailer->blockedNotices)->toHaveCount(1);
});

it('ends the session and denies access if deactivated before the lock is acquired', function () {
    $this->locks->onLock = function () {
        $operator = $this->operators->byId($this->ana);
        $operator->deactivate();
        $this->operators->save($operator);
    };

    expect(fn () => confirmTotp('123456'))->toThrow(AuthorizationDenied::class)
        ->and($this->session->current())->toBeNull();
});
