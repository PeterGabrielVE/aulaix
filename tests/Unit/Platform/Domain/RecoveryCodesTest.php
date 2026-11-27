<?php

use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Platform\FakeRecoveryCodeHasher;

/**
 * SPEC-014 PLT-R01.5, R01.10; SPEC-017 RSP-NF02: ten recovery codes, kept as
 * keyed hashes, each used once; a new set replaces the old one whole.
 */
function hasher(string $secret = 'test-recovery-code-key'): FakeRecoveryCodeHasher
{
    return new FakeRecoveryCodeHasher($secret);
}

function codes(?FakeRecoveryCodeHasher $with = null): RecoveryCodes
{
    return RecoveryCodes::fromPlain(array_map(fn (int $n) => "code-{$n}", range(1, 10)), $with ?? hasher());
}

it('keeps ten codes, only as hashes', function () {
    $codes = codes();
    $plainSha256 = hash('sha256', RecoveryCodes::normalize('code-1'));

    expect($codes->remaining())->toBe(10)
        ->and(json_encode($codes->hashes()))->not->toContain('code-1')
        ->and(json_encode($codes->hashes()))->not->toContain($plainSha256);
});

it('uses a code once, and says how many are left', function () {
    $hasher = hasher();
    $codes = codes($hasher)->use('CODE 4', $hasher);

    expect($codes->remaining())->toBe(9)
        ->and(fn () => $codes->use('code-4', $hasher))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => $codes->use('code-99', $hasher))->toThrow(BusinessRuleViolation::class);
});

it('tells whether a code would be accepted only with the matching secret', function () {
    $hasher = hasher('secret-a');
    $other = hasher('secret-b');
    $codes = codes($hasher)->use('code-1', $hasher);

    expect($codes->accepts('code-2', $hasher))->toBeTrue()
        ->and($codes->accepts('code-2', $other))->toBeFalse()
        ->and($codes->accepts('code-1', $hasher))->toBeFalse()
        ->and($codes->accepts('otro', $hasher))->toBeFalse();
});

it('needs exactly ten codes', function () {
    expect(fn () => RecoveryCodes::fromPlain(['a', 'b'], hasher()))->toThrow(InvalidArgumentException::class);
});
