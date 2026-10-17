<?php

use AulaX\IdentityAccess\Application\Port\HandoffTokens;
use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Shared\Domain\InstitutionId;

/**
 * SPEC-004 IDG-NF03: the token that opens an institution without asking for
 * the password again is single-use, short-lived and bound to one account and
 * one institution.
 */
function handoffTokens(): HandoffTokens
{
    return app(HandoffTokens::class);
}

it('opens the institution it was issued for, once', function () {
    $token = handoffTokens()->issue(AccountId::fromInt(7), InstitutionId::fromInt(3));

    expect(handoffTokens()->consume($token, InstitutionId::fromInt(3)))->toEqual(AccountId::fromInt(7))
        ->and(handoffTokens()->consume($token, InstitutionId::fromInt(3)))->toBeNull();
});

it('still works 60 seconds later but not 61', function (int $seconds, bool $works) {
    $token = handoffTokens()->issue(AccountId::fromInt(7), InstitutionId::fromInt(3));

    $this->travel($seconds)->seconds();

    expect(handoffTokens()->consume($token, InstitutionId::fromInt(3)) !== null)->toBe($works);
})->with([
    '60 s' => [60, true],
    '61 s' => [61, false],
]);

it('does not open another institution', function () {
    $token = handoffTokens()->issue(AccountId::fromInt(7), InstitutionId::fromInt(3));

    expect(handoffTokens()->consume($token, InstitutionId::fromInt(4)))->toBeNull()
        // Refused tokens are not burnt: the right institution still opens.
        ->and(handoffTokens()->consume($token, InstitutionId::fromInt(3)))->toEqual(AccountId::fromInt(7));
});

it('rejects a forged or tampered token', function (string $token) {
    expect(handoffTokens()->consume($token, InstitutionId::fromInt(3)))->toBeNull();
})->with([
    'garbage' => ['not-a-token'],
    'empty' => [''],
    'unsigned payload' => [base64_encode('{"account":7,"institution":3,"expires":9999999999,"nonce":"x"}')],
]);
