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
