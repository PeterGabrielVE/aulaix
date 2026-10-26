<?php

use AulaX\Platform\Application\Operators\ChangeOperatorRoleHandler;
use AulaX\Platform\Application\Operators\ListOperatorsQuery;
use AulaX\Platform\Application\Operators\RegenerateRecoveryCodesHandler;
use AulaX\Platform\Application\Operators\ResetOperatorMfaHandler;
use AulaX\Platform\Application\Operators\SetOperatorActiveHandler;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Platform\FakeAuditContext;
use Tests\Fixtures\Platform\FakeOperatorSession;
use Tests\Fixtures\Platform\InMemoryOperatorInvitationRepository;
use Tests\Fixtures\Platform\InMemoryOperatorRepository;
use Tests\Fixtures\Platform\RecordingOperatorMailer;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-014 PLT-R01.10, R03.3 – R03.8, NF05 and V4: what a SuperAdmin may do
 * with the other operators, and what nobody may do.
 */
beforeEach(function () {
    $this->operators = new InMemoryOperatorRepository;
    $this->invitations = new InMemoryOperatorInvitationRepository;
    $this->session = new FakeOperatorSession;
    $this->mailer = new RecordingOperatorMailer;
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-26 10:00:00'));
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->auditContext = new FakeAuditContext;

    $this->boss = managedOperator($this->operators, 'jefa@aulax.ve', OperatorRole::SuperAdmin);
    $this->other = managedOperator($this->operators, 'otro@aulax.ve', OperatorRole::SuperAdmin);
    $this->support = managedOperator($this->operators, 'soporte@aulax.ve', OperatorRole::Support);
    signInAs($this->boss);
});

function managedOperator(InMemoryOperatorRepository $operators, string $email, OperatorRole $role): OperatorId
{
    $operator = Operator::invite('Operador', OperatorEmail::fromString($email), $role);
    $operator->activate('hash:x', "SECRET-{$email}", 1);
    $id = $operators->save($operator);
    $operators->saveRecoveryCodes($id, RecoveryCodes::fromPlain(array_map(fn (int $n) => "{$email}-{$n}", range(1, 10))));

    return $id;
}

function signInAs(OperatorId $id): void
{
    test()->session->start(test()->operators->byId($id), test()->clock->now());
}

function changeRole(OperatorId $target, OperatorRole $role): void
{
    $test = test();
    (new ChangeOperatorRoleHandler($test->operators, $test->session, $test->auditContext, $test->audit, $test->transactions))($target, $role);
}

function setActive(OperatorId $target, bool $active): void
{
    $test = test();
    (new SetOperatorActiveHandler($test->operators, $test->invitations, $test->session, $test->auditContext, $test->audit, $test->transactions))($target, $active);
}

function resetMfa(OperatorId $target): void
{
    $test = test();
    (new ResetOperatorMfaHandler($test->operators, $test->invitations, $test->session, $test->mailer, $test->auditContext, $test->audit, $test->transactions, $test->clock))($target);
}

it('lists the operators, without secrets', function () {
    $list = (new ListOperatorsQuery($this->operators, $this->session))();

    expect(array_column($list, 'email'))->toBe(['jefa@aulax.ve', 'otro@aulax.ve', 'soporte@aulax.ve'])
        ->and($list[2])->toBe(['id' => $this->support->value, 'name' => 'Operador', 'email' => 'soporte@aulax.ve', 'role' => 'support', 'status' => 'active', 'hasTotp' => true]);
});

it('changes the role of an operator, closing its sessions, and records it', function () {
    $version = $this->operators->byId($this->support)->sessionVersion;

    changeRole($this->support, OperatorRole::SuperAdmin);

    expect($this->operators->byId($this->support)->role)->toBe(OperatorRole::SuperAdmin)
        ->and($this->operators->byId($this->support)->sessionVersion)->toBe($version + 1)
        ->and($this->audit->types())->toBe(['platform.operator.role_changed'])
        ->and($this->auditContext->platformOperator)->toBe($this->boss->value);
});

it('deactivates an operator, closing its sessions and revoking its pending links (V4)', function () {
    $this->invitations->issue(OperatorInvitation::issue($this->support, InvitationPurpose::MfaReset, hash('sha256', 'pendiente'), $this->clock->now()));

    setActive($this->support, false);

    expect($this->operators->byId($this->support)->status)->toBe(OperatorStatus::Inactive)
        ->and($this->invitations->byTokenHash(hash('sha256', 'pendiente'))->isUsableAt($this->clock->now()))->toBeFalse()
        ->and($this->audit->types())->toBe(['platform.operator.deactivated']);

    setActive($this->support, true);

    expect($this->operators->byId($this->support)->canLogIn())->toBeTrue()
        ->and($this->audit->types())->toBe(['platform.operator.deactivated', 'platform.operator.reactivated']);
});

it('never leaves the platform without an active SuperAdmin, nor lets one act on itself', function () {
    setActive($this->other, false);

    expect(fn () => changeRole($this->boss, OperatorRole::Support))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => setActive($this->boss, false))->toThrow(BusinessRuleViolation::class, 'No puedes quitarte el nivel ni desactivarte a ti mismo.');
});

it('lets no Support manage operators (PLT-R03.8)', function (Closure $action) {
    signInAs($this->support);

    expect(fn () => $action->call($this))->toThrow(AuthorizationDenied::class);
})->with([
    'change a role' => [fn () => changeRole($this->other, OperatorRole::Support)],
    'deactivate' => [fn () => setActive($this->other, false)],
    'reset the second factor' => [fn () => resetMfa($this->other)],
    'list' => [fn () => (new ListOperatorsQuery($this->operators, $this->session))()],
]);

it('resets the second factor of another operator and sends the only way back (PLT-R03.7, V4)', function () {
    $this->invitations->issue(OperatorInvitation::issue($this->support, InvitationPurpose::Onboarding, hash('sha256', 'viejo'), $this->clock->now()));
    $version = $this->operators->byId($this->support)->sessionVersion;

    resetMfa($this->support);

    $operator = $this->operators->byId($this->support);

    expect($operator->totpSecret)->toBeNull()
        ->and($operator->canLogIn())->toBeFalse()
        ->and($operator->sessionVersion)->toBe($version + 1)
        ->and($this->operators->recoveryCodes($this->support)->remaining())->toBe(0)
        ->and($this->invitations->byTokenHash(hash('sha256', 'viejo'))->isUsableAt($this->clock->now()))->toBeFalse()
        ->and($this->mailer->sent[0]['purpose'])->toBe(InvitationPurpose::MfaReset)
        ->and($this->invitations->byTokenHash(hash('sha256', $this->mailer->lastToken()))->isUsableAt($this->clock->now()))->toBeTrue()
        ->and($this->audit->types())->toBe(['platform.operator.mfa_reset']);
});

it('does not reset its own second factor', function () {
    expect(fn () => resetMfa($this->boss))->toThrow(BusinessRuleViolation::class);
});

it('regenerates the own recovery codes, voiding the old ones (PLT-R01.10)', function () {
    signInAs($this->support);

    $codes = (new RegenerateRecoveryCodesHandler($this->operators, $this->session, $this->auditContext, $this->audit, $this->transactions))();

    $stored = $this->operators->recoveryCodes($this->support);

    expect($codes)->toHaveCount(10)
        ->and($stored->accepts($codes[0]))->toBeTrue()
        ->and($stored->accepts('soporte@aulax.ve-1'))->toBeFalse()
        ->and($this->audit->types())->toBe(['platform.operator.recovery_codes_regenerated']);
});
