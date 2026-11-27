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
