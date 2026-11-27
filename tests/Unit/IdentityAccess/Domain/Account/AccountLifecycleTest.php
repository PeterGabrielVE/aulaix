<?php

use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountState;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\EmailChangeNotUsable;
use AulaX\IdentityAccess\Domain\Account\EmailChangeRequest;
use AulaX\IdentityAccess\Domain\Account\HashedPassword;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-004 IDG-R05, R08: what the owner does with their own account.
 */
function utc(string $when): DateTimeImmutable
{
    return new DateTimeImmutable($when, new DateTimeZone('UTC'));
}

function ana(AccountState $state = AccountState::Active, ?ConsolidationState $consolidation = null): Account
{
    return Account::reconstitute(AccountId::fromInt(7), EmailAddress::fromString('ana@colegio.ve'), PersonName::fromString('Ana'), $state, $consolidation, utc('2026-01-01'));
}

it('changes its name', function () {
    expect(ana()->changeName(PersonName::fromString('Ana Pérez'))->name->value)->toBe('Ana Pérez');
});

it('keeps its email until the new one is verified, within a day', function () {
    $issued = EmailChangeRequest::issue(EmailAddress::fromString('nueva@colegio.ve'), utc('2026-10-16 08:00'));
    $requested = ana()->requestEmailChange($issued->request);

    expect($requested->email->value)->toBe('ana@colegio.ve')
        ->and($requested->pendingEmailChange->email->value)->toBe('nueva@colegio.ve')
        ->and($requested->pendingEmailChange->tokenHash)->not->toBe($issued->token);

    $confirmed = $requested->confirmEmailChange($issued->token, utc('2026-10-17 08:00'));

    expect($confirmed->email->value)->toBe('nueva@colegio.ve')
        ->and($confirmed->emailVerifiedAt->format('Y-m-d H:i'))->toBe('2026-10-17 08:00')
        ->and($confirmed->pendingEmailChange)->toBeNull();
});

it('refuses an expired or wrong email verification link', function (string $token, string $when) {
    $issued = EmailChangeRequest::issue(EmailAddress::fromString('nueva@colegio.ve'), utc('2026-10-16 08:00'));
    $requested = ana()->requestEmailChange($issued->request);

    expect(fn () => $requested->confirmEmailChange($token === 'right' ? $issued->token : $token, utc($when)))
        ->toThrow(EmailChangeNotUsable::class);
})->with([
    'expired' => ['right', '2026-10-17 08:01'],
    'wrong token' => ['otro', '2026-10-16 09:00'],
]);

it('can not change the email while it shares it with other historical accounts', function () {
    $issued = EmailChangeRequest::issue(EmailAddress::fromString('nueva@colegio.ve'), utc('2026-10-16 08:00'));

    expect(fn () => ana(consolidation: ConsolidationState::Pending)->requestEmailChange($issued->request))
        ->toThrow(BusinessRuleViolation::class);
});

it('is deleted only after 30 calendar days of grace, which can be cancelled', function () {
    $pending = ana()->requestDeletion(utc('2026-10-16 08:00'));

    expect($pending->state)->toBe(AccountState::PendingDeletion)
        ->and($pending->deletionRequestedAt->format('Y-m-d'))->toBe('2026-10-16')
        ->and($pending->deletionDueAt->format('Y-m-d H:i'))->toBe('2026-11-15 08:00')
        ->and($pending->isDueForClosing(utc('2026-11-15 07:59')))->toBeFalse()
        ->and($pending->isDueForClosing(utc('2026-11-15 08:00')))->toBeTrue();

    $cancelled = $pending->cancelDeletion();

    expect($cancelled->state)->toBe(AccountState::Active)
        ->and($cancelled->deletionDueAt)->toBeNull();
});

it('drops a pending email change when its deletion is requested', function () {
    $issued = EmailChangeRequest::issue(EmailAddress::fromString('nueva@colegio.ve'), utc('2026-10-16 08:00'));

    expect(ana()->requestEmailChange($issued->request)->requestDeletion(utc('2026-10-16 09:00'))->pendingEmailChange)->toBeNull();
});

it('only requests or cancels a deletion from the right state', function () {
    expect(fn () => ana(AccountState::PendingDeletion)->requestDeletion(utc('2026-10-16')))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => ana()->cancelDeletion())->toThrow(BusinessRuleViolation::class);
});

it('postpones its closing, or is closed for good with unusable credentials', function () {
    $pending = ana()->requestDeletion(utc('2026-10-16 08:00'));

    expect($pending->postponeDeletion(utc('2026-11-22 08:00'))->deletionDueAt->format('Y-m-d'))->toBe('2026-11-22');

    $closed = $pending->close(HashedPassword::fromHash('random-unusable-hash'));

    expect($closed->state)->toBe(AccountState::Closed)
        ->and($closed->newPassword->value())->toBe('random-unusable-hash')
        ->and(fn () => ana()->close(HashedPassword::fromHash('x')))->toThrow(LogicException::class);
});
