<?php

use AulaX\Platform\Application\Port\TotpCodes;

/**
 * SPEC-014 PLT-R01.2 (ADR 0010): TOTP codes as in RFC 6238 (SHA-1, 30 s,
 * 6 digits), accepted one step either side, and returning the step used so
 * the operator can refuse it next time (PLT-NF03).
 */
const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'; // base32 of "12345678901234567890"

function totpCodes(): TotpCodes
{
    return app(TotpCodes::class);
}

function atUnixTime(int $timestamp): DateTimeImmutable
{
    return (new DateTimeImmutable)->setTimestamp($timestamp);
}

it('accepts the codes of the RFC 6238 test vectors, and tells their time step', function (int $timestamp, string $code) {
    expect(totpCodes()->verify(RFC_SECRET, $code, atUnixTime($timestamp)))->toBe(intdiv($timestamp, 30));
})->with([
    [59, '287082'],
    [1111111109, '081804'],
    [1234567890, '005924'],
]);

it('accepts the code of the step before or after, and nothing further', function () {
    $code = '005924'; // step of 1234567890

    expect(totpCodes()->verify(RFC_SECRET, $code, atUnixTime(1234567890 + 30)))->toBe(intdiv(1234567890, 30))
        ->and(totpCodes()->verify(RFC_SECRET, $code, atUnixTime(1234567890 - 30)))->toBe(intdiv(1234567890, 30))
        ->and(totpCodes()->verify(RFC_SECRET, $code, atUnixTime(1234567890 + 90)))->toBeNull();
});

it('refuses a wrong or malformed code', function (string $code) {
    expect(totpCodes()->verify(RFC_SECRET, $code, atUnixTime(59)))->toBeNull();
})->with(['000000', '28708', 'abcdef', '']);

it('creates a new secret, and a QR code for the authenticator app', function () {
    $secret = totpCodes()->newSecret();

    expect($secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->and(totpCodes()->newSecret())->not->toBe($secret)
        ->and(totpCodes()->qrSvg('ana@aulax.ve', $secret))->toContain('<svg');
});
