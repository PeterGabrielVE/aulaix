<?php

use AulaX\Enrollment\Domain\AgeRule;
use AulaX\Enrollment\Infrastructure\ConfigAgeRules;

/**
 * SPEC-010 MAT-R03.6, R03.7: the ages of each grade come from the
 * configuration, checked when it is read.
 */
it('reads the ages of each grade and the cutoff', function () {
    $rules = new ConfigAgeRules('12-31', ['primaria_1' => [6, 5, 7], 'media_5' => [16, 15, 18]]);

    expect($rules->cutoff())->toBe('12-31')
        ->and($rules->of('primaria_1'))->toEqual(new AgeRule('primaria_1', 6, 5, 7))
        ->and($rules->of('media_5'))->toEqual(new AgeRule('media_5', 16, 15, 18))
        ->and($rules->of('maternal_1'))->toBeNull();
});

it('refuses a wrong cutoff, an unknown grade or ages out of order', function (string $cutoff, array $ages) {
    expect(fn () => new ConfigAgeRules($cutoff, $ages))->toThrow(InvalidArgumentException::class);
})->with([
    'cutoff not a date' => ['31-12', []],
    'cutoff with a year' => ['2026-12-31', []],
    'unknown grade' => ['12-31', ['sexto' => [11, 10, 12]]],
    'missing ages' => ['12-31', ['primaria_1' => [6, 5]]],
    'out of order' => ['12-31', ['primaria_1' => [8, 5, 7]]],
]);

it('the configuration of the platform has the ages decided for Primaria and Media General', function () {
    $config = require dirname(__DIR__, 4).'/config/enrollment.php';
    $rules = new ConfigAgeRules($config['age_cutoff'], $config['ages']);

    expect($rules->of('primaria_1'))->toEqual(new AgeRule('primaria_1', 6, 5, 7))
        ->and($rules->of('primaria_6'))->toEqual(new AgeRule('primaria_6', 11, 10, 12))
        ->and($rules->of('media_1'))->toEqual(new AgeRule('media_1', 12, 11, 13))
        ->and($rules->of('media_4'))->toEqual(new AgeRule('media_4', 15, 14, 16))
        ->and($rules->of('media_5'))->toEqual(new AgeRule('media_5', 16, 15, 18))
        ->and($rules->of('preescolar_3'))->toBeNull();
});
