<?php

use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Domain\SuperAdminRules;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-014 PLT-R03.5, R03.6: the platform never runs out of active
 * SuperAdmins, and nobody demotes or deactivates themselves.
 */
function ruleOperator(int $id, OperatorRole $role = OperatorRole::SuperAdmin): Operator
{
    return Operator::reconstitute(
        OperatorId::fromInt($id), "Operador {$id}", OperatorEmail::fromString("op{$id}@aulax.ve"), $role,
        OperatorStatus::Active, 'hash', 'SECRET', 1, 1,
    );
}

it('lets a SuperAdmin demote or deactivate another one while others remain', function () {
    SuperAdminRules::check(ruleOperator(1), ruleOperator(2), demotes: true, activeSuperAdmins: 2);
    SuperAdminRules::check(ruleOperator(1), ruleOperator(2), demotes: true, activeSuperAdmins: 3);

    expect(true)->toBeTrue();
});

it('refuses to leave the platform without an active SuperAdmin', function () {
    expect(fn () => SuperAdminRules::check(ruleOperator(1), ruleOperator(2), demotes: true, activeSuperAdmins: 1))
        ->toThrow(BusinessRuleViolation::class, 'La plataforma no puede quedarse sin un Superadministrador activo.');
});

it('refuses that an operator demotes or deactivates itself', function () {
    expect(fn () => SuperAdminRules::check(ruleOperator(1), ruleOperator(1), demotes: true, activeSuperAdmins: 5))
        ->toThrow(BusinessRuleViolation::class, 'No puedes quitarte el nivel ni desactivarte a ti mismo.');
});

it('never counts changes that keep a SuperAdmin, or touch a Support', function () {
    SuperAdminRules::check(ruleOperator(1), ruleOperator(2, OperatorRole::Support), demotes: true, activeSuperAdmins: 1);
    SuperAdminRules::check(ruleOperator(1), ruleOperator(2), demotes: false, activeSuperAdmins: 1);

    expect(true)->toBeTrue();
});
