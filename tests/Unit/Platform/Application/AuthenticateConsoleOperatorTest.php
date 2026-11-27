<?php

use AulaX\Platform\Application\Access\AuthenticateConsoleOperatorHandler;
use AulaX\Platform\Application\Access\CodeAttempt;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Platform\CountingOperatorLock;
use Tests\Fixtures\Platform\FakeAuditContext;
use Tests\Fixtures\Platform\FakeRecoveryCodeHasher;
use Tests\Fixtures\Platform\FakeTotpCodes;
use Tests\Fixtures\Platform\InMemoryOperatorRepository;
use Tests\Fixtures\Platform\RecordingOperatorMailer;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-017 RSP-R06.1 – R06.7: console operator authentication requires an
 * interactive TOTP, rejects recovery codes, shares TOTP single-use and lockout
 * state with the panel, and records every refusal in the platform log.
 */
beforeEach(function () {
    $this->operators = new InMemoryOperatorRepository;
    $this->locks = new CountingOperatorLock;
    $this->totp = new FakeTotpCodes;
    $this->hasher = new FakeRecoveryCodeHasher;
    $this->mailer = new RecordingOperatorMailer;
    $this->auditContext = new FakeAuditContext;
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->clock = new FixedClock(new DateTimeImmutable('2026-11-27 10:00:00'));

    $operator = Operator::invite('Luis', OperatorEmail::fromString('luis@aulax.ve'), OperatorRole::Support);
    $operator->activate('hash:x', 'SECRET-LUIS', step: 100);
    $this->luis = $this->operators->save($operator);
    $this->operators->saveRecoveryCodes($this->luis, RecoveryCodes::fromPlain(array_map(fn (int $n) => "rec-{$n}", range(1, 10)), $this->hasher));
    $this->totp->codes['SECRET-LUIS'] = ['123456' => 101, '111111' => 100];
});

function authConsole(string $email, ?string $code): mixed
{
    $test = test();
    $attempt = new CodeAttempt(
        $test->operators, $test->locks, $test->totp, $test->hasher, $test->mailer,
        $test->audit, $test->transactions, $test->clock,
    );

    return (new AuthenticateConsoleOperatorHandler(
        $test->operators, $attempt, $test->auditContext, $test->audit, $test->transactions,
    ))($email, $code);
}

it('authenticates an active operator with a fresh TOTP and advances totpLastStep', function () {
    $id = authConsole('LUIS@aulax.ve', '123456');

    expect($id->equals($this->luis))->toBeTrue()
        ->and($this->operators->byId($this->luis)->totpLastStep)->toBe(101)
        ->and($this->audit->types())->toBe([]);
});

it('refuses a non-interactive call and audits without counting a code failure (RSP-R06.4)', function () {
    expect(fn () => authConsole('luis@aulax.ve', null))->toThrow(BusinessRuleViolation::class)
        ->and($this->operators->byId($this->luis)->codeFailures)->toBe([])
        ->and($this->audit->types())->toBe(['platform.console_auth.refused'])
        ->and($this->auditContext->consoleOperator)->toBe('luis@aulax.ve');
});

it('rejects a recovery code in the console, counts it as a code failure and audits (RSP-R06.5, R06.7)', function () {
    expect(fn () => authConsole('luis@aulax.ve', 'rec-1'))->toThrow(BusinessRuleViolation::class)
        ->and($this->operators->byId($this->luis)->codeFailures)->toHaveCount(1)
        ->and($this->operators->recoveryCodes($this->luis)->remaining())->toBe(10)
        ->and($this->audit->types())->toBe(['platform.console_auth.refused']);
});

it('rejects reused TOTP, unknown or inactive operator, and triggers lockout on the tenth failure', function () {
    expect(fn () => authConsole('nadie@aulax.ve', '123456'))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => authConsole('luis@aulax.ve', '111111'))->toThrow(BusinessRuleViolation::class);

    for ($i = 2; $i <= 10; $i++) {
        expect(fn () => authConsole('luis@aulax.ve', '999999'))->toThrow(BusinessRuleViolation::class);
    }

    expect($this->operators->byId($this->luis)->isBlockedAt($this->clock->now))->toBeTrue()
        ->and($this->audit->types())->toContain('platform.operator.blocked')
        ->and(fn () => authConsole('luis@aulax.ve', '123456'))->toThrow(BusinessRuleViolation::class);
});
