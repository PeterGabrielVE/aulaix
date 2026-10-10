<?php

use AulaX\Catalogs\Application\Port\GeographySource;
use AulaX\Catalogs\Domain\InvalidCatalog;
use AulaX\Catalogs\Infrastructure\Source\JsonGeographySource;

it('reads the bundled file as a valid catalog of all of Venezuela', function () {
    $states = app(GeographySource::class)->load()->states();

    $municipalities = array_merge(...array_map(fn ($state) => $state->municipalities, $states));
    $parishes = array_merge(...array_map(fn ($municipality) => $municipality->parishes, $municipalities));

    expect($states)->toHaveCount(24)
        ->and($municipalities)->toHaveCount(335)
        ->and($parishes)->toHaveCount(1140);
});

it('fails on a file that is not valid JSON', function () {
    $source = new JsonGeographySource(base_path('tests/Fixtures/Catalogs/geography-not-json.json'));

    expect(fn () => $source->load())->toThrow(JsonException::class);
});

it('rejects a file whose structure is incomplete', function () {
    $source = new JsonGeographySource(base_path('tests/Fixtures/Catalogs/geography-missing-parishes.json'));

    expect(fn () => $source->load())->toThrow(InvalidCatalog::class, 'VE-A-01');
});
