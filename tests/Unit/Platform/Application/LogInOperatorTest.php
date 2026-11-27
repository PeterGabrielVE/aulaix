<?php

use AulaX\Platform\Application\Access\LogInOperatorHandler;
use AulaX\Platform\Application\Access\LogOutOperatorHandler;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Platform\FakeAuditContext;
use Tests\Fixtures\Platform\FakeOperatorPasswords;
use Tests\Fixtures\Platform\FakeOperatorSession;
use Tests\Fixtures\Platform\FakeTotpCodes;
use Tests\Fixtures\Platform\InMemoryOperatorRepository;
use Tests\Fixtures\Platform\RecordingSecurityLog;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-014 PLT-R01.2 – R01.5, R01.9, R07.1, R07.3 and V5: an operator
 * enters with email, password and code at once; every failure looks the
 * same, costs the same and is recorded the same.
 */
beforeEach(function () {
    $this->operators = new InMemoryOperatorRepository;
    $this->passwords = new FakeOperatorPasswords;
    $this->totp = new FakeTotpCodes;
    $this->session = new FakeOperatorSession;
    $this->log = new RecordingSecurityLog;
    $this->auditContext = new FakeAuditContext;
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);

    $operator = Operator::invite('Ana Rivas', OperatorEmail::fromString('ana@aulax.ve'), OperatorRole::Support);
    $operator->activate('hash:clave-segura', 'SECRET-ANA', step: 100);
    $this->ana = $this->operators->save($operator);
    $this->operators->saveRecoveryCodes($this->ana, RecoveryCodes::fromPlain(array_map(fn (int $n) => "rec-{$n}", range(1, 10))));
    $this->totp->codes['SECRET-ANA'] = ['123456' => 101, '111111' => 100];
});

function platformLogIn(string $email, string $password, string $code): ?int
{
    $test = test();

    return (new LogInOperatorHandler(
        $test->operators, $test->passwords, $test->totp, $test->session, $test->log,
        $test->auditContext, $test->audit, $test->transactions, new FixedClock,
    ))($email, $password, $code);
}

/**
 * @return array{message: string, field: ?string}
 */
function platformLoginFailure(string $email, string $password, string $code): array
{
    try {
        platformLogIn($email, $password, $code);
    } catch (BusinessRuleViolation $failure) {
        return ['message' => $failure->getMessage(), 'field' => $failure->field()];
    }

    test()->fail('The login was accepted.');
}

it('opens the session with a valid TOTP, and records it', function () {
    platformLogIn('ANA@aulax.ve', 'clave-segura', '123456');

    expect($this->session->current()->equals($this->ana))->toBeTrue()
        ->and($this->operators->byId($this->ana)->totpLastStep)->toBe(101)
        ->and($this->audit->types())->toBe(['platform.login.succeeded'])
        ->and($this->auditContext->platformOperator)->toBe($this->ana->value);
});

it('accepts a recovery code once, and says how many are left', function () {
    expect(platformLogIn('ana@aulax.ve', 'clave-segura', 'rec-3'))->toBe(9)
        ->and($this->operators->recoveryCodes($this->ana)->remaining())->toBe(9)
        ->and(platformLoginFailure('ana@aulax.ve', 'clave-segura', 'rec-3')['message'])->toBe('Datos de acceso incorrectos.');
});

it('gives one answer, one event and no clue for every failure (V5)', function (Closure $prepare, string $email, string $password, string $code) {
    $prepare->call($this);

    expect(platformLoginFailure($email, $password, $code))->toBe(['message' => 'Datos de acceso incorrectos.', 'field' => 'email'])
        ->and($this->session->current())->toBeNull()
        ->and($this->audit->types())->toBe(['platform.login.failed'])
        ->and(json_encode(array_map(fn ($entry) => [$entry['event']->subject, $entry['event']->changes], $this->audit->recorded)))
        ->not->toContain('ana@aulax.ve')
        ->and($this->log->failures)->toHaveCount(1);
})->with([
    'unknown email' => [fn () => null, 'nadie@aulax.ve', 'clave-segura', '123456'],
    'wrong password' => [fn () => null, 'ana@aulax.ve', 'otra', '123456'],
    'wrong code' => [fn () => null, 'ana@aulax.ve', 'clave-segura', '999999'],
    'code already used' => [fn () => null, 'ana@aulax.ve', 'clave-segura', '111111'],
    'recovery code spent' => [function () {
        $this->operators->saveRecoveryCodes($this->ana, $this->operators->recoveryCodes($this->ana)->use('rec-1'));
    }, 'ana@aulax.ve', 'clave-segura', 'rec-1'],
    'operator deactivated' => [function () {
        $operator = $this->operators->byId($this->ana);
        $operator->deactivate();
        $this->operators->save($operator);
    }, 'ana@aulax.ve', 'clave-segura', '123456'],
    'not an email' => [fn () => null, 'no-es-correo', 'clave-segura', '123456'],
]);

it('still compares a password and checks a code when the email is unknown (V5)', function () {
    platformLoginFailure('nadie@aulax.ve', 'clave-segura', '123456');

    expect($this->passwords->checks)->toBe(1)
        ->and($this->totp->verifications)->toBe(1);
});

it('still checks the code when the password is wrong (V5)', function () {
    platformLoginFailure('ana@aulax.ve', 'otra', '123456');

    expect($this->totp->verifications)->toBe(1)
        ->and($this->operators->byId($this->ana)->totpLastStep)->toBe(100);
});

it('ends the session on logout, and records it', function () {
    platformLogIn('ana@aulax.ve', 'clave-segura', '123456');

    (new LogOutOperatorHandler($this->session, $this->auditContext, $this->audit, $this->transactions))();

    expect($this->session->current())->toBeNull()
        ->and($this->audit->types())->toBe(['platform.login.succeeded', 'platform.session.ended']);
});
