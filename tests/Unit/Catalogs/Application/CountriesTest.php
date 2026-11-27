<?php

use AulaX\Catalogs\Application\Countries;

/**
 * SPEC-009 EST-R01.4: the countries a student may be born in, ISO 3166-1
 * with their Spanish names.
 */
it('lists every ISO 3166-1 country with its Spanish name, by name', function () {
    $countries = (new Countries)->all();
    $names = array_column($countries, 'name');

    expect($countries)->toHaveCount(249)
        ->and($countries)->toContain(['code' => 'VE', 'name' => 'Venezuela'], ['code' => 'US', 'name' => 'Estados Unidos'])
        ->and($names[0])->toBe('Afganistán');
});

it('tells whether a code is a country', function () {
    expect((new Countries)->exists('CO'))->toBeTrue()
        ->and((new Countries)->exists('XK'))->toBeFalse()
        ->and((new Countries)->exists('EU'))->toBeFalse();
});
