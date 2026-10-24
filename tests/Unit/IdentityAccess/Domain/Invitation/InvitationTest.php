<?php

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use AulaX\IdentityAccess\Domain\Invitation\Invitation;
use AulaX\IdentityAccess\Domain\Invitation\InvitationNotUsable;
use AulaX\IdentityAccess\Domain\Invitation\InvitationStatus;
use AulaX\IdentityAccess\Domain\Invitation\IssuedInvitation;
use AulaX\IdentityAccess\Domain\Role\RoleSet;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\InstitutionId;

function at(string $when): DateTimeImmutable
{
    return new DateTimeImmutable($when, new DateTimeZone('UTC'));
}

function issuedOn(string $when, array $roles = ['Docente']): IssuedInvitation
{
    return Invitation::issue(
        InstitutionId::fromInt(1),
        EmailAddress::fromString('ana@colegio.ve'),
        PersonName::fromString('Ana Pérez'),
        RoleSet::of(...$roles),
        AccountId::fromInt(9),
        at($when),
    );
}

it('is pending for seven days from when it was sent', function () {
    $invitation = issuedOn('2026-10-01 12:00')->invitation;

    expect($invitation->status)->toBe(InvitationStatus::Pending)
        ->and($invitation->expiresAt->format(DATE_ATOM))->toBe('2026-10-08T12:00:00+00:00');
});

it('keeps only the hash of its token', function () {
    $issued = issuedOn('2026-10-01 12:00');

    expect($issued->invitation->tokenHash)->not->toBe($issued->token)
        ->and($issued->invitation->tokenHash)->toBe(hash('sha256', $issued->token))
        ->and(strlen($issued->token))->toBeGreaterThanOrEqual(64);
});

it('requires at least one role', function () {
    expect(fn () => issuedOn('2026-10-01 12:00', roles: []))->toThrow(BusinessRuleViolation::class);
});

it('is accepted with its token while it lasts', function () {
    $issued = issuedOn('2026-10-01 12:00');

    $accepted = $issued->invitation->accept($issued->token, AccountId::fromInt(5), at('2026-10-07 12:00'));

    expect($accepted->status)->toBe(InvitationStatus::Accepted)
        ->and($accepted->acceptedBy->value)->toBe(5);
});

it('can not be used with a wrong token, once expired, accepted or cancelled', function (Closure $use) {
    expect($use)->toThrow(InvitationNotUsable::class);
})->with([
    'wrong token' => [fn () => issuedOn('2026-10-01 12:00')->invitation->accept('not-the-token', AccountId::fromInt(5), at('2026-10-02 12:00'))],
    'after seven days' => [function () {
        $issued = issuedOn('2026-10-01 12:00');

        return $issued->invitation->accept($issued->token, AccountId::fromInt(5), at('2026-10-08 12:00:01'));
    }],
    'accepted already' => [function () {
        $issued = issuedOn('2026-10-01 12:00');

        return $issued->invitation->accept($issued->token, AccountId::fromInt(5), at('2026-10-02 12:00'))
            ->accept($issued->token, AccountId::fromInt(5), at('2026-10-02 12:00'));
    }],
    'cancelled' => [function () {
        $issued = issuedOn('2026-10-01 12:00');

        return $issued->invitation->cancel()->accept($issued->token, AccountId::fromInt(5), at('2026-10-02 12:00'));
    }],
]);

it('still accepts the link of an invitation sent before the migration', function () {
    // Laravel's password broker stored invitation tokens hashed with bcrypt.
    $legacy = Invitation::reconstitute(
        7,
        InstitutionId::fromInt(1),
        EmailAddress::fromString('ana@colegio.ve'),
        PersonName::fromString('Ana Pérez'),
        RoleSet::of('Docente'),
        password_hash('token-from-the-old-email', PASSWORD_BCRYPT),
        at('2026-10-08 12:00'),
        InvitationStatus::Pending,
        null,
        null,
    );

    expect($legacy->accept('token-from-the-old-email', AccountId::fromInt(5), at('2026-10-02 12:00'))->status)->toBe(InvitationStatus::Accepted)
        ->and(fn () => $legacy->accept('another-token', AccountId::fromInt(5), at('2026-10-02 12:00')))->toThrow(InvitationNotUsable::class);
});

it('invalidates the previous link when corrected or resent', function (string $how) {
    $first = issuedOn('2026-10-01 12:00');

    $renewed = $how === 'corrected'
        ? $first->invitation->correct(PersonName::fromString('Ana María Pérez'), EmailAddress::fromString('anamaria@colegio.ve'), at('2026-10-03 12:00'))
        : $first->invitation->resend(at('2026-10-03 12:00'));

    expect(fn () => $renewed->invitation->accept($first->token, AccountId::fromInt(5), at('2026-10-03 13:00')))->toThrow(InvitationNotUsable::class)
        ->and($renewed->invitation->accept($renewed->token, AccountId::fromInt(5), at('2026-10-03 13:00'))->status)->toBe(InvitationStatus::Accepted)
        ->and($renewed->invitation->expiresAt->format(DATE_ATOM))->toBe('2026-10-10T12:00:00+00:00');
})->with(['corrected', 'resent']);

it('is accepted without its token by the account that logged in with its email, while it lasts', function () {
    $invitation = issuedOn('2026-10-01 12:00')->invitation;

    $accepted = $invitation->acceptBy(AccountId::fromInt(5), at('2026-10-08 12:00'));

    expect($accepted->status)->toBe(InvitationStatus::Accepted)
        ->and($accepted->acceptedBy->value)->toBe(5)
        ->and(fn () => $invitation->acceptBy(AccountId::fromInt(5), at('2026-10-08 12:01')))->toThrow(InvitationNotUsable::class)
        ->and(fn () => $accepted->acceptBy(AccountId::fromInt(5), at('2026-10-02 12:00')))->toThrow(InvitationNotUsable::class);
});
