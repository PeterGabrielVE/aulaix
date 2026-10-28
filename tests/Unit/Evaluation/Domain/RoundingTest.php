<?php

use AulaX\Evaluation\Domain\Rounding;

/**
 * SPEC-011 EVA-R03.1, NF03: to the nearest whole number, ,5 up, with exact
 * integer arithmetic.
 */
it('rounds a fraction to the nearest whole number, half up', function (int $numerator, int $denominator, int $rounded) {
    expect(Rounding::nearest($numerator, $denominator))->toBe($rounded);
})->with([
    '9,49' => [949, 100, 9],
    '9,5' => [950, 100, 10],
    '9,499999' => [9499999, 1000000, 9],
    '19,5' => [1950, 100, 20],
    '0,5' => [1, 2, 1],
    'one third' => [10, 3, 3],
    'two thirds of 20' => [40, 3, 13],
    '29/2 = 14,5' => [29, 2, 15],
    'whole' => [12, 1, 12],
]);

it('refuses a denominator that is not positive', function () {
    expect(fn () => Rounding::nearest(1, 0))->toThrow(InvalidArgumentException::class);
});
