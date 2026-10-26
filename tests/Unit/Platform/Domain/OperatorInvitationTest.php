<?php

use AulaX\Platform\Domain\InvitationPurpose;
use AulaX\Platform\Domain\OperatorId;
use AulaX\Platform\Domain\OperatorInvitation;
use AulaX\Platform\Domain\OperatorInvitationRejected;

/**
 * SPEC-014 PLT-R03.2 (V4): a link to join the panel or enrol a TOTP again
 * lasts 48 hours, is used once and can be revoked.
 */
function issuedAt(string $when, InvitationPurpose $purpose = InvitationPurpose::Onboarding): OperatorInvitation
{
    return OperatorInvitation::issue(OperatorId::fromInt(3), $purpose, hash('sha256', 'token'), new DateTimeImmutable($when));
}

it('lasts 48 hours, not a second more', function () {
    $invitation = issuedAt('2026-10-26 10:00:00');

    expect($invitation->expiresAt)->toEqual(new DateTimeImmutable('2026-10-28 10:00:00'))
        ->and($invitation->isUsableAt(new DateTimeImmutable('2026-10-28 09:59:59')))->toBeTrue()
        ->and($invitation->isUsableAt(new DateTimeImmutable('2026-10-28 10:00:00')))->toBeFalse();
});

it('is used once', function () {
    $invitation = issuedAt('2026-10-26 10:00:00');

    $invitation->use(new DateTimeImmutable('2026-10-26 11:00:00'));

    expect($invitation->usedAt)->toEqual(new DateTimeImmutable('2026-10-26 11:00:00'))
        ->and(fn () => $invitation->use(new DateTimeImmutable('2026-10-26 11:01:00')))->toThrow(OperatorInvitationRejected::class);
});

it('refuses an expired or revoked link with the same answer', function () {
    $expired = issuedAt('2026-10-20 10:00:00');
    $revoked = issuedAt('2026-10-26 10:00:00');
    $revoked->revoke();

    $now = new DateTimeImmutable('2026-10-26 12:00:00');

    expect(fn () => $expired->use($now))->toThrow(OperatorInvitationRejected::class, 'El enlace no es válido o ha caducado.')
        ->and(fn () => $revoked->use($now))->toThrow(OperatorInvitationRejected::class, 'El enlace no es válido o ha caducado.');
});

it('knows what it is for', function () {
    expect(issuedAt('2026-10-26', InvitationPurpose::MfaReset)->purpose)->toBe(InvitationPurpose::MfaReset)
        ->and(array_map(fn (InvitationPurpose $purpose) => $purpose->value, InvitationPurpose::cases()))->toBe(['onboarding', 'mfa_reset']);
});
