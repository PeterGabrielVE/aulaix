<?php

use AulaX\IdentityAccess\Domain\Account\EmailAddress;
use AulaX\IdentityAccess\Domain\Account\InvalidEmailAddress;

it('normalizes an email to lower case without surrounding spaces', function () {
    expect(EmailAddress::fromString('  Ana.Perez@Colegio.EDU.ve ')->value)->toBe('ana.perez@colegio.edu.ve');
});

it('keeps provider-specific details such as dots and plus tags', function () {
    expect(EmailAddress::fromString('ana.perez+notas@gmail.com')->value)->toBe('ana.perez+notas@gmail.com');
});

it('treats two spellings of the same address as equal', function () {
    expect(EmailAddress::fromString('ANA@colegio.ve')->equals(EmailAddress::fromString(' ana@colegio.ve')))->toBeTrue();
});

it('rejects a value that is not an email address', function (string $email) {
    expect(fn () => EmailAddress::fromString($email))->toThrow(InvalidEmailAddress::class);
})->with([
    'empty' => [''],
    'no at sign' => ['ana.colegio.ve'],
    'no domain' => ['ana@'],
    'too long' => [str_repeat('a', 250).'@ve.ve'],
]);
