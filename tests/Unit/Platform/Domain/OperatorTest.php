<?php

use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-014 PLT-R01.4, R01.6, NF03: a platform operator, who may only enter
 * once active with a confirmed TOTP, and never with the same code twice.
 */
function invitedOperator(OperatorRole $role = OperatorRole::Support): Operator
{
    return Operator::invite('Ana Rivas', OperatorEmail::fromString(' Ana@AulaX.ve '), $role);
}

function activeOperator(): Operator
{
    $operator = invitedOperator();
    $operator->activate('hash', 'SECRET', step: 100);

    return $operator;
}

it('knows its emails, roles and states', function () {
    expect(OperatorEmail::fromString(' Ana@AulaX.ve ')->value)->toBe('ana@aulax.ve')
        ->and(fn () => OperatorEmail::fromString('no-es-correo'))->toThrow(BusinessRuleViolation::class)
        ->and(array_map(fn (OperatorRole $role) => $role->value, OperatorRole::cases()))->toBe(['support', 'super_admin'])
        ->and(OperatorRole::SuperAdmin->canManage())->toBeTrue()
        ->and(OperatorRole::Support->canManage())->toBeFalse()
        ->and(array_map(fn (OperatorStatus $status) => $status->value, OperatorStatus::cases()))->toBe(['pending', 'active', 'inactive'])
        ->and(OperatorId::fromInt(7)->value)->toBe(7)
        ->and(fn () => OperatorId::fromInt(0))->toThrow(InvalidArgumentException::class);
});

it('starts pending, without password nor TOTP, and may not enter', function () {
    $operator = invitedOperator(OperatorRole::SuperAdmin);

    expect($operator->status)->toBe(OperatorStatus::Pending)
        ->and($operator->role)->toBe(OperatorRole::SuperAdmin)
        ->and($operator->passwordHash)->toBeNull()
        ->and($operator->totpSecret)->toBeNull()
        ->and($operator->canLogIn())->toBeFalse();
});

it('becomes active with its password and a confirmed TOTP', function () {
    $operator = activeOperator();

    expect($operator->status)->toBe(OperatorStatus::Active)
        ->and($operator->passwordHash)->toBe('hash')
        ->and($operator->totpSecret)->toBe('SECRET')
        ->and($operator->canLogIn())->toBeTrue();
});

it('accepts each TOTP step once, and none older than the last one used', function () {
    $operator = activeOperator();

    $operator->useTotpStep(101);

    expect(fn () => $operator->useTotpStep(101))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => $operator->useTotpStep(99))->toThrow(BusinessRuleViolation::class);

    $operator->useTotpStep(102);

    expect($operator->totpLastStep)->toBe(102);
});

it('may not enter once deactivated, and enters again once reactivated', function () {
    $operator = activeOperator();
    $version = $operator->sessionVersion;

    $operator->deactivate();

    expect($operator->canLogIn())->toBeFalse()
        ->and($operator->sessionVersion)->toBe($version + 1);

    $operator->reactivate();

    expect($operator->canLogIn())->toBeTrue();
});

it('closes its sessions when its role changes, and only then', function () {
    $operator = activeOperator();
    $version = $operator->sessionVersion;

    $operator->changeRole(OperatorRole::Support);
    expect($operator->sessionVersion)->toBe($version);

    $operator->changeRole(OperatorRole::SuperAdmin);
    expect($operator->role)->toBe(OperatorRole::SuperAdmin)
        ->and($operator->sessionVersion)->toBe($version + 1);
});

it('loses its TOTP and its sessions when its second factor is reset, and may not enter until it enrols again', function () {
    $operator = activeOperator();
    $version = $operator->sessionVersion;

    $operator->resetMfa();

    expect($operator->totpSecret)->toBeNull()
        ->and($operator->totpLastStep)->toBeNull()
        ->and($operator->sessionVersion)->toBe($version + 1)
        ->and($operator->canLogIn())->toBeFalse();

    $operator->enrolTotp('NEW-SECRET', step: 300);

    expect($operator->canLogIn())->toBeTrue()
        ->and($operator->passwordHash)->toBe('hash');
});

it('activates only once, and enrols a TOTP only when it has none', function () {
    $operator = activeOperator();

    expect(fn () => $operator->activate('other', 'OTHER', 1))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => $operator->enrolTotp('OTHER', 1))->toThrow(BusinessRuleViolation::class);
});

it('blocks for 15 minutes and bumps session version on the tenth consecutive code failure', function () {
    $operator = activeOperator();
    $version = $operator->sessionVersion;
    $at = new DateTimeImmutable('2026-11-27 10:00:00');

    for ($i = 1; $i <= 9; $i++) {
        expect($operator->failCode($at->modify("+{$i} seconds"), "198.51.100.{$i}"))->toBeFalse()
            ->and($operator->isBlockedAt($at->modify("+{$i} seconds")))->toBeFalse();
    }

    $tenthAt = $at->modify('+10 seconds');
    expect($operator->failCode($tenthAt, '203.0.113.10'))->toBeTrue()
        ->and($operator->codeFailures)->toHaveCount(10)
        ->and($operator->codeFailures[0]->ip)->toBe('198.51.100.1')
        ->and($operator->codeFailures[9]->ip)->toBe('203.0.113.10')
        ->and($operator->blockedUntil)->toEqual($tenthAt->modify('+15 minutes'))
        ->and($operator->isBlockedAt($tenthAt->modify('+14 minutes 59 seconds')))->toBeTrue()
        ->and($operator->sessionVersion)->toBe($version + 1);
});

it('clears failures after a successful code outside lockout', function () {
    $operator = activeOperator();
    $at = new DateTimeImmutable('2026-11-27 10:00:00');

    for ($i = 1; $i <= 5; $i++) {
        $operator->failCode($at->modify("+{$i} seconds"), "198.51.100.{$i}");
    }

    $operator->useTotpStep(101);
    expect($operator->codeFailures)->toBe([]);

    for ($i = 1; $i <= 5; $i++) {
        $operator->failCode($at->modify("+{$i} minutes"), "198.51.100.{$i}");
    }

    $operator->recordRecoveryCodeUsed();
    expect($operator->codeFailures)->toBe([]);
});

it('accepts codes again with failures reset once the 15-minute lockout expires', function () {
    $operator = activeOperator();
    $at = new DateTimeImmutable('2026-11-27 10:00:00');

    for ($i = 1; $i <= 10; $i++) {
        $operator->failCode($at, "198.51.100.{$i}");
    }

    $after = $at->modify('+15 minutes');
    expect($operator->isBlockedAt($after))->toBeFalse();

    // A new failure after expiration starts counting from 1 again, not 11.
    expect($operator->failCode($after->modify('+1 second'), '198.51.100.99'))->toBeFalse()
        ->and($operator->codeFailures)->toHaveCount(1)
        ->and($operator->isBlockedAt($after->modify('+1 second')))->toBeFalse();
});

it('resetting MFA lifts an active lockout and clears failures', function () {
    $operator = activeOperator();
    $at = new DateTimeImmutable('2026-11-27 10:00:00');

    for ($i = 1; $i <= 10; $i++) {
        $operator->failCode($at, "198.51.100.{$i}");
    }

    expect($operator->isBlockedAt($at->modify('+1 minute')))->toBeTrue();

    $operator->resetMfa();

    expect($operator->blockedUntil)->toBeNull()
        ->and($operator->codeFailures)->toBe([])
        ->and($operator->isBlockedAt($at->modify('+1 minute')))->toBeFalse();
});

it('offers no method to lift a lockout early', function () {
    $operator = activeOperator();
    $at = new DateTimeImmutable('2026-11-27 10:00:00');

    for ($i = 1; $i <= 10; $i++) {
        $operator->failCode($at, "198.51.100.{$i}");
    }

    // Even deactivating and reactivating preserves the active lockout (RSP-R03.10).
    $operator->deactivate();
    $operator->reactivate();

    expect($operator->isBlockedAt($at->modify('+5 minutes')))->toBeTrue();

    $methods = array_map(fn (ReflectionMethod $m) => $m->getName(), (new ReflectionClass(Operator::class))->getMethods(ReflectionMethod::IS_PUBLIC));
    expect($methods)->not->toContain('unblock')
        ->and($methods)->not->toContain('clearLockout')
        ->and($methods)->not->toContain('unlock');
});
