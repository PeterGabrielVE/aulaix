<?php

use AulaX\Catalogs\Domain\Geography\GeographicCatalog;
use AulaX\Catalogs\Domain\Geography\MunicipalityEntry;
use AulaX\Catalogs\Domain\Geography\ParishEntry;
use AulaX\Catalogs\Domain\Geography\StateEntry;
use AulaX\Catalogs\Domain\InvalidCatalog;

function distritoCapital(): StateEntry
{
    return StateEntry::of('VE-A', 'Distrito Capital', [
        MunicipalityEntry::of('VE-A-01', 'Libertador', [
            ParishEntry::of('VE-A-01-01', 'Altagracia'),
            ParishEntry::of('VE-A-01-02', 'Catedral'),
        ]),
    ]);
}

it('keeps the states, municipalities and parishes of a valid catalog', function () {
    $catalog = GeographicCatalog::of(distritoCapital());

    expect($catalog->states())->toHaveCount(1)
        ->and($catalog->states()[0]->municipalities[0]->parishes)->toHaveCount(2);
});

it('rejects two states with the same code', function () {
    expect(fn () => GeographicCatalog::of(distritoCapital(), distritoCapital()))
        ->toThrow(InvalidCatalog::class, 'VE-A');
});

it('rejects two parishes with the same code', function () {
    $state = StateEntry::of('VE-A', 'Distrito Capital', [
        MunicipalityEntry::of('VE-A-01', 'Libertador', [
            ParishEntry::of('VE-A-01-01', 'Altagracia'),
            ParishEntry::of('VE-A-01-01', 'Catedral'),
        ]),
    ]);

    expect(fn () => GeographicCatalog::of($state))->toThrow(InvalidCatalog::class, 'VE-A-01-01');
});

it('rejects a state without municipalities', function () {
    expect(fn () => GeographicCatalog::of(StateEntry::of('VE-A', 'Distrito Capital', [])))
        ->toThrow(InvalidCatalog::class, 'VE-A');
});

it('rejects a municipality without parishes', function () {
    $state = StateEntry::of('VE-A', 'Distrito Capital', [MunicipalityEntry::of('VE-A-01', 'Libertador', [])]);

    expect(fn () => GeographicCatalog::of($state))->toThrow(InvalidCatalog::class, 'VE-A-01');
});

it('rejects a municipality whose code belongs to another state', function () {
    $state = StateEntry::of('VE-A', 'Distrito Capital', [
        MunicipalityEntry::of('VE-B-01', 'Anaco', [ParishEntry::of('VE-B-01-01', 'Anaco')]),
    ]);

    expect(fn () => GeographicCatalog::of($state))->toThrow(InvalidCatalog::class, 'VE-B-01');
});

it('rejects a parish whose code belongs to another municipality', function () {
    $state = StateEntry::of('VE-A', 'Distrito Capital', [
        MunicipalityEntry::of('VE-A-01', 'Libertador', [ParishEntry::of('VE-A-02-01', 'Catedral')]),
    ]);

    expect(fn () => GeographicCatalog::of($state))->toThrow(InvalidCatalog::class, 'VE-A-02-01');
});

it('rejects a state that uses a code of the provisional first-version catalog', function () {
    $state = StateEntry::of('VE-01', 'Amazonas', [
        MunicipalityEntry::of('VE-01-01', 'Atures', [ParishEntry::of('VE-01-01-01', 'Fernando Girón Tovar')]),
    ]);

    expect(fn () => GeographicCatalog::of($state))->toThrow(InvalidCatalog::class, 'VE-01');
});

it('rejects an entry without a name', function () {
    expect(fn () => ParishEntry::of('VE-A-01-01', ''))->toThrow(InvalidCatalog::class, 'VE-A-01-01');
});
