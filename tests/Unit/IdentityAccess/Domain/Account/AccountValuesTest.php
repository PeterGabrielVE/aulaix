<?php

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\IdentityAccess\Domain\Account\HashedPassword;
use AulaX\IdentityAccess\Domain\Account\PersonName;
use AulaX\Shared\Domain\BusinessRuleViolation;

it('keeps a person name without surrounding spaces', function () {
    expect(PersonName::fromString('  Ana Pérez ')->value)->toBe('Ana Pérez');
});

it('rejects an empty or overlong person name', function (string $name) {
    expect(fn () => PersonName::fromString($name))->toThrow(BusinessRuleViolation::class);
})->with([['   '], [str_repeat('a', 256)]]);

it('compares person names ignoring case and spacing, to spot duplicate accounts', function () {
    expect(PersonName::fromString('Ana  Pérez')->sameAs(PersonName::fromString(' ana pérez')))->toBeTrue()
        ->and(PersonName::fromString('Ana Pérez')->sameAs(PersonName::fromString('Ana Rodríguez')))->toBeFalse();
});

it('wraps a positive account id', function () {
    expect(AccountId::fromInt(9)->value)->toBe(9)
        ->and(fn () => AccountId::fromInt(0))->toThrow(InvalidArgumentException::class);
});

it('never exposes a password hash when printed', function () {
    $hash = HashedPassword::fromHash('$2y$12$abcdefghijklmnopqrstuv');

    expect((string) $hash)->not->toContain('$2y$')
        ->and(print_r($hash, true))->not->toContain('$2y$');
});
