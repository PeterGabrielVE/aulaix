<?php

use AulaX\Platform\Application\Operators\AcceptInvitationHandler;
use AulaX\Platform\Application\Operators\InviteOperatorHandler;
use AulaX\Platform\Application\Operators\OpenInvitationQuery;
use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\Operator;
use AulaX\Platform\Domain\OperatorEmail;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorInvitationRejected;
use AulaX\Platform\Domain\OperatorRole;
use AulaX\Platform\Domain\OperatorStatus;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Platform\FakeAuditContext;
use Tests\Fixtures\Platform\FakeOperatorPasswords;
use Tests\Fixtures\Platform\FakeTotpCodes;
use Tests\Fixtures\Platform\InMemoryOperatorInvitationRepository;
use Tests\Fixtures\Platform\InMemoryOperatorRepository;
use Tests\Fixtures\Platform\InMemoryPendingTotpSecrets;
use Tests\Fixtures\Platform\RecordingOperatorMailer;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-014 PLT-R01.6, R01.10, R02.1, R03.1, R03.2 and V4: an operator is
 * invited by a link that lasts 48 hours, is used once, and is the only way
 * to set its password and enrol its TOTP.
 */
beforeEach(function () {
    $this->operators = new InMemoryOperatorRepository;
    $this->invitations = new InMemoryOperatorInvitationRepository;
    $this->mailer = new RecordingOperatorMailer;
    $this->totp = new FakeTotpCodes;
    $this->secrets = new InMemoryPendingTotpSecrets;
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-26 10:00:00'));
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->auditContext = new FakeAuditContext;
});

function platformInvite(string $email = 'ana@aulax.ve', ?OperatorId $by = null, OperatorRole $role = OperatorRole::Support): OperatorId
{
    $test = test();

    return (new InviteOperatorHandler($test->operators, $test->invitations, $test->mailer, $test->auditContext, $test->audit, $test->transactions, $test->clock))(
        'Ana Rivas', $email, $role, $by,
    );
}

function openPlatformLink(string $token): object
{
    $test = test();

    return (new OpenInvitationQuery($test->operators, $test->invitations, $test->totp, $test->secrets, $test->clock))($token);
}

/**
 * @return list<string>
 */
function acceptPlatformLink(string $token, ?string $password, string $code): array
{
    $test = test();

    return (new AcceptInvitationHandler(
        $test->operators, $test->invitations, new FakeOperatorPasswords, $test->totp, $test->secrets,
        $test->auditContext, $test->audit, $test->transactions, $test->clock,
    ))($token, $password, $code);
}

function storedPlatformOperator(InMemoryOperatorRepository $operators, OperatorRole $role = OperatorRole::SuperAdmin): OperatorId
{
    $operator = Operator::invite('Jefa', OperatorEmail::fromString("jefa-{$role->value}@aulax.ve"), $role);
    $operator->activate('hash:x', 'SECRET-JEFA', 1);

    return $operators->save($operator);
}

it('invites a pending operator by email, keeping only the hash of the token, and records it', function () {
    $id = platformInvite();

    $token = $this->mailer->lastToken();

    expect($this->operators->byId($id)->status)->toBe(OperatorStatus::Pending)
        ->and($this->mailer->sent[0]['purpose'])->toBe(InvitationPurpose::Onboarding)
        ->and(strlen($token))->toBeGreaterThanOrEqual(40)
        ->and($this->invitations->byTokenHash(hash('sha256', $token)))->not->toBeNull()
        ->and($this->invitations->byTokenHash($token))->toBeNull()
        ->and($this->audit->types())->toBe(['platform.operator.invited']);
});

it('refuses an email that already belongs to an operator', function () {
    platformInvite();

    expect(fn () => platformInvite('ANA@aulax.ve'))->toThrow(BusinessRuleViolation::class, 'Ese correo ya pertenece a un operador.');
});

it('lets only an active SuperAdmin invite from the panel', function () {
    $support = storedPlatformOperator($this->operators, OperatorRole::Support);
    $boss = storedPlatformOperator($this->operators);

    expect(fn () => platformInvite('luis@aulax.ve', $support))->toThrow(AuthorizationDenied::class);

    platformInvite('luis@aulax.ve', $boss);

    expect($this->mailer->sent)->toHaveCount(1);
});

it('shows the QR of a secret kept in the session until a code confirms it', function () {
    platformInvite();
    $token = $this->mailer->lastToken();

    $first = openPlatformLink($token);
    $second = openPlatformLink($token);

    expect($first->secret)->toBe($second->secret)
        ->and($first->qrSvg)->toContain('<svg')
        ->and($first->email)->toBe('ana@aulax.ve')
        ->and($first->needsPassword)->toBeTrue()
        ->and($this->operators->byId(OperatorId::fromInt(1))->totpSecret)->toBeNull();
});

it('activates the operator with its password and a confirmed TOTP, and gives ten recovery codes once', function () {
    $id = platformInvite();
    $token = $this->mailer->lastToken();
    $secret = openPlatformLink($token)->secret;
    $this->totp->codes[$secret] = ['654321' => 5000];

    $codes = acceptPlatformLink($token, 'una-clave-larga', '654321');

    $operator = $this->operators->byId($id);

    expect($operator->canLogIn())->toBeTrue()
        ->and($operator->passwordHash)->toBe('hash:una-clave-larga')
        ->and($operator->totpSecret)->toBe($secret)
        ->and($operator->totpLastStep)->toBe(5000)
        ->and($codes)->toHaveCount(10)
        ->and(count(array_unique($codes)))->toBe(10)
        ->and($this->operators->recoveryCodes($id)->accepts($codes[0]))->toBeTrue()
        ->and($this->secrets->secrets)->toBe([])
        ->and($this->audit->types())->toBe(['platform.operator.invited', 'platform.operator.activated']);
});

it('keeps the operator pending and the link usable when the code is wrong', function () {
    $id = platformInvite();
    $token = $this->mailer->lastToken();
    openPlatformLink($token);

    expect(fn () => acceptPlatformLink($token, 'una-clave-larga', '000000'))->toThrow(BusinessRuleViolation::class, 'El código no es correcto.')
        ->and($this->operators->byId($id)->status)->toBe(OperatorStatus::Pending)
        ->and($this->invitations->byTokenHash(hash('sha256', $token))->isUsableAt($this->clock->now()))->toBeTrue();
});

it('uses a link once (V4)', function () {
    platformInvite();
    $token = $this->mailer->lastToken();
    $secret = openPlatformLink($token)->secret;
    $this->totp->codes[$secret] = ['654321' => 5000];
    acceptPlatformLink($token, 'una-clave-larga', '654321');

    expect(fn () => openPlatformLink($token))->toThrow(OperatorInvitationRejected::class)
        ->and(fn () => acceptPlatformLink($token, 'una-clave-larga', '654321'))->toThrow(OperatorInvitationRejected::class);
});

it('refuses an expired, revoked or unknown link with the same answer (V4)', function (Closure $spoil) {
    platformInvite();
    $token = $spoil->call($this, $this->mailer->lastToken());

    expect(fn () => openPlatformLink($token))->toThrow(OperatorInvitationRejected::class, 'El enlace no es válido o ha caducado.')
        ->and(fn () => acceptPlatformLink($token, 'una-clave-larga', '654321'))->toThrow(OperatorInvitationRejected::class, 'El enlace no es válido o ha caducado.');
})->with([
    'expired' => [function (string $token) {
        $this->clock->now = new DateTimeImmutable('2026-10-28 10:00:00');

        return $token;
    }],
    'revoked by a newer link' => [function (string $token) {
        $this->invitations->issue(OperatorInvitation::issue(OperatorId::fromInt(1), InvitationPurpose::Onboarding, hash('sha256', 'nuevo'), $this->clock->now()));

        return $token;
    }],
    'unknown' => [fn (string $token) => 'no-existe'],
]);

it('enrols a TOTP again after a reset, without touching the password', function () {
    $operator = Operator::invite('Ana Rivas', OperatorEmail::fromString('ana@aulax.ve'), OperatorRole::Support);
    $operator->activate('hash:vieja', 'SECRET-VIEJO', 1);
    $operator->resetMfa();
    $id = $this->operators->save($operator);
    $this->invitations->issue(OperatorInvitation::issue($id, InvitationPurpose::MfaReset, hash('sha256', 'reinscribir'), $this->clock->now()));

    $view = openPlatformLink('reinscribir');
    $this->totp->codes[$view->secret] = ['111222' => 7000];
    acceptPlatformLink('reinscribir', null, '111222');

    expect($view->needsPassword)->toBeFalse()
        ->and($this->operators->byId($id)->passwordHash)->toBe('hash:vieja')
        ->and($this->operators->byId($id)->canLogIn())->toBeTrue()
        ->and($this->audit->types())->toBe(['platform.operator.totp_enrolled']);
});
