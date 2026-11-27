<?php

use AulaX\Platform\Domain\RecoveryCodes;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-014 PLT-R01.5, R01.10: ten recovery codes, kept as hashes, each used
 * once; a new set replaces the old one whole.
 */
function codes(): RecoveryCodes
{
    return RecoveryCodes::fromPlain(array_map(fn (int $n) => "code-{$n}", range(1, 10)));
}

it('keeps ten codes, only as hashes', function () {
    $codes = codes();

    expect($codes->remaining())->toBe(10)
        ->and(json_encode($codes->hashes()))->not->toContain('code-1');
});

it('uses a code once, and says how many are left', function () {
    $codes = codes()->use('code-4');

    expect($codes->remaining())->toBe(9)
        ->and(fn () => $codes->use('code-4'))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => $codes->use('code-99'))->toThrow(BusinessRuleViolation::class);
});

it('tells whether a code would be accepted', function () {
    $codes = codes()->use('code-1');

    expect($codes->accepts('code-2'))->toBeTrue()
        ->and($codes->accepts('code-1'))->toBeFalse()
        ->and($codes->accepts('otro'))->toBeFalse();
});

it('needs exactly ten codes', function () {
    expect(fn () => RecoveryCodes::fromPlain(['a', 'b']))->toThrow(InvalidArgumentException::class);
});
