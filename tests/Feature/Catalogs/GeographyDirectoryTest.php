<?php

use AulaX\Catalogs\Application\Geography\GeographyDirectory;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-006 INS-R01.5: the geography catalog, for the location selectors.
 */
function geography(): array
{
    $state = DB::table('states')->insertGetId(['name' => 'Zulia', 'code' => 'T-ZU']);
    $municipality = DB::table('municipalities')->insertGetId(['state_id' => $state, 'name' => 'Maracaibo', 'code' => 'T-ZU-01']);
    $parish = DB::table('parishes')->insertGetId(['municipality_id' => $municipality, 'name' => 'Chiquinquirá', 'code' => 'T-ZU-01-01']);

    return [$state, $municipality, $parish];
}

test('it walks from states to their parishes', function () {
    [$state, $municipality, $parish] = geography();
    $directory = app(GeographyDirectory::class);

    expect(collect($directory->states())->firstWhere('id', $state)['name'])->toBe('Zulia')
        ->and($directory->municipalitiesOf($state))->toBe([['id' => $municipality, 'name' => 'Maracaibo']])
        ->and($directory->parishesOf($municipality))->toBe([['id' => $parish, 'name' => 'Chiquinquirá']]);
});

test('it tells whether a parish exists and where it is', function () {
    [$state, $municipality, $parish] = geography();
    $directory = app(GeographyDirectory::class);

    expect($directory->parishExists($parish))->toBeTrue()
        ->and($directory->parishExists(999_999))->toBeFalse()
        ->and($directory->locationOf($parish))->toBe(['state_id' => $state, 'municipality_id' => $municipality, 'parish_id' => $parish]);
});
