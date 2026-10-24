<?php

use AulaX\IdentityAccess\Domain\Account\Account;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\AccountState;
use AulaX\IdentityAccess\Domain\Account\ConsolidationState;
use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\HashedPassword;
use AulaX\IdentityAccess\Domain\Account\PersonName;

it('registers an active account whose email is verified by the invitation link itself', function () {
    $account = Account::register(
        EmailAddress::fromString('Ana@Colegio.ve'),
        PersonName::fromString('Ana Pérez'),
        HashedPassword::fromHash('hash'),
        new DateTimeImmutable('2026-10-15 09:00', new DateTimeZone('UTC')),
    );

    expect($account->id)->toBeNull()
        ->and($account->email->value)->toBe('ana@colegio.ve')
        ->and($account->state)->toBe(AccountState::Active)
        ->and($account->emailVerifiedAt?->format(DATE_ATOM))->toBe('2026-10-15T09:00:00+00:00')
        ->and($account->isUnderConsolidation())->toBeFalse();
});

it('knows when it is a historical account waiting to be consolidated', function (ConsolidationState $state) {
    $account = Account::reconstitute(
        AccountId::fromInt(3),
        EmailAddress::fromString('ana@colegio.ve'),
        PersonName::fromString('Ana Pérez'),
        AccountState::Active,
        $state,
        null,
    );

    expect($account->isUnderConsolidation())->toBeTrue();
})->with([ConsolidationState::Pending, ConsolidationState::Review]);

it('is only active while its state says so', function (AccountState $state, bool $active) {
    $account = Account::reconstitute(AccountId::fromInt(3), EmailAddress::fromString('ana@colegio.ve'), PersonName::fromString('Ana'), $state, null, null);

    expect($account->isActive())->toBe($active);
})->with([
    [AccountState::Active, true],
    [AccountState::PendingDeletion, false],
    [AccountState::Closed, false],
]);

it('carries a new password only when one was set, keeping everything else', function () {
    $account = Account::reconstitute(AccountId::fromInt(7), EmailAddress::fromString('ana@colegio.ve'), PersonName::fromString('Ana'), AccountState::Active, null, null);

    $changed = $account->changePassword(HashedPassword::fromHash('nuevo-hash'));

    expect($account->newPassword)->toBeNull()
        ->and($changed->newPassword?->value())->toBe('nuevo-hash')
        ->and($changed->id)->toEqual($account->id)
        ->and($changed->email)->toEqual($account->email);
});

it('becomes a regular account once consolidated', function () {
    $pending = Account::reconstitute(AccountId::fromInt(7), EmailAddress::fromString('ana@colegio.ve'), PersonName::fromString('Ana'), AccountState::Active, ConsolidationState::Pending, null);

    expect($pending->consolidated()->isUnderConsolidation())->toBeFalse()
        ->and($pending->consolidated()->id)->toEqual($pending->id);
});

it('can not be consolidated unless it is awaiting consolidation', function () {
    $regular = Account::reconstitute(AccountId::fromInt(7), EmailAddress::fromString('ana@colegio.ve'), PersonName::fromString('Ana'), AccountState::Active, null, null);

    expect(fn () => $regular->consolidated())->toThrow(LogicException::class);
});

it('is kept apart only when support reviewed it', function () {
    $review = Account::reconstitute(AccountId::fromInt(7), EmailAddress::fromString('ana@colegio.ve'), PersonName::fromString('Ana'), AccountState::Active, ConsolidationState::Review, null);
    $pending = Account::reconstitute(AccountId::fromInt(8), EmailAddress::fromString('ana@colegio.ve'), PersonName::fromString('Ana'), AccountState::Active, ConsolidationState::Pending, null);

    expect($review->keptSeparate()->consolidation)->toBe(ConsolidationState::Separated)
        // Still sharing its email: each password keeps opening only its institution.
        ->and($review->keptSeparate()->isUnderConsolidation())->toBeTrue()
        ->and(fn () => $pending->keptSeparate())->toThrow(LogicException::class);
});
