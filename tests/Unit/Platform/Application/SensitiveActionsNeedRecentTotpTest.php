<?php

use AulaX\Platform\Application\Consolidation\ResolveCaseHandler;
use AulaX\Platform\Application\Institutions\SetInstitutionActiveHandler;
use AulaX\Platform\Application\Operators\ChangeOperatorRoleHandler;
use AulaX\Platform\Application\Operators\InviteOperatorHandler;
use AulaX\Platform\Application\Operators\RegenerateRecoveryCodesHandler;
use AulaX\Platform\Application\Operators\ResetOperatorMfaHandler;
use AulaX\Platform\Application\Operators\SetOperatorActiveHandler;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Platform\CountingOperatorLock;
use Tests\Fixtures\Platform\FakeAuditContext;
use Tests\Fixtures\Platform\FakeConsolidationDesk;
use Tests\Fixtures\Platform\FakeOperatorSession;
use Tests\Fixtures\Platform\FakePlatformInstitutions;
use Tests\Fixtures\Platform\FakeRecoveryCodeHasher;
use Tests\Fixtures\Platform\InMemoryOperatorInvitationRepository;
use Tests\Fixtures\Platform\InMemoryOperatorRepository;
use Tests\Fixtures\Platform\RecordingOperatorMailer;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-017 RSP-NF03: every sensitive use case enforces a recent TOTP (<= 10
 * minutes) inside the Application layer itself, independent of route middleware.
 */
beforeEach(function () {
    $this->operators = new InMemoryOperatorRepository;
    $this->invitations = new InMemoryOperatorInvitationRepository;
    $this->locks = new CountingOperatorLock;
    $this->hasher = new FakeRecoveryCodeHasher;
    $this->session = new FakeOperatorSession;
    $this->mailer = new RecordingOperatorMailer;
    $this->institutions = new FakePlatformInstitutions;
    $this->institutions->add(7, 'Colegio Norte');
    $this->desk = new FakeConsolidationDesk;
    $this->desk->add('ana@example.com', 11, 12);
    $this->clock = new FixedClock(new DateTimeImmutable('2026-11-27 10:00:00'));
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->auditContext = new FakeAuditContext;

    $boss = Operator::invite('Jefa', OperatorEmail::fromString('jefa@aulax.ve'), OperatorRole::SuperAdmin);
    $boss->activate('hash:x', 'SECRET-JEFA', 1);
    $this->boss = $this->operators->save($boss);

    $support = Operator::invite('Soporte', OperatorEmail::fromString('soporte@aulax.ve'), OperatorRole::Support);
    $support->activate('hash:x', 'SECRET-SOPORTE', 1);
    $this->support = $this->operators->save($support);

    // Session started 11 minutes ago: active session, but stale TOTP (> 10 min).
    $this->session->start($this->operators->byId($this->boss), $this->clock->now->modify('-11 minutes'));
});

it('refuses every sensitive platform use case when TOTP confirmation is older than 10 minutes', function (Closure $action) {
    expect(fn () => $action->call($this))->toThrow(AuthorizationDenied::class, 'Confirma tu código antes de hacer esto.');
})->with([
    'set operator active' => [fn () => (new SetOperatorActiveHandler($this->operators, $this->invitations, $this->locks, $this->session, $this->auditContext, $this->audit, $this->transactions, $this->clock))($this->support, false)],
    'change operator role' => [fn () => (new ChangeOperatorRoleHandler($this->operators, $this->locks, $this->session, $this->auditContext, $this->audit, $this->transactions, $this->clock))($this->support, OperatorRole::SuperAdmin)],
    'reset operator mfa' => [fn () => (new ResetOperatorMfaHandler($this->operators, $this->invitations, $this->locks, $this->session, $this->mailer, $this->auditContext, $this->audit, $this->transactions, $this->clock))($this->support)],
    'regenerate recovery codes' => [fn () => (new RegenerateRecoveryCodesHandler($this->operators, $this->locks, $this->hasher, $this->session, $this->auditContext, $this->audit, $this->transactions, $this->clock))()],
    'invite operator from panel' => [fn () => (new InviteOperatorHandler($this->operators, $this->invitations, $this->session, $this->mailer, $this->auditContext, $this->audit, $this->transactions, $this->clock))('Nuevo', 'nuevo@aulax.ve', OperatorRole::Support, $this->boss)],
    'set institution active' => [fn () => (new SetInstitutionActiveHandler($this->operators, $this->session, $this->institutions, $this->institutions, $this->auditContext, $this->audit, $this->transactions, $this->clock))(7, false, 'Mora')],
    'resolve consolidation case' => [fn () => (new ResolveCaseHandler($this->operators, $this->session, $this->desk, $this->auditContext, $this->clock))([11, 12], 'separate', 'Distintas personas', null)],
]);

it('still allows inviting the bootstrap operator from the console without a session', function () {
    $this->session->end();

    $id = (new InviteOperatorHandler($this->operators, $this->invitations, $this->session, $this->mailer, $this->auditContext, $this->audit, $this->transactions, $this->clock))(
        'Bootstrap', 'boot@aulax.ve', OperatorRole::SuperAdmin, null,
    );

    expect($this->operators->byId($id))->not->toBeNull();
});
