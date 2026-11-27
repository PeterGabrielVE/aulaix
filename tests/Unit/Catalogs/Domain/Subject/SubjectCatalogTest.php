<?php

use AulaX\Catalogs\Domain\EducationLevel;
use AulaX\Catalogs\Domain\InvalidCatalog;
use AulaX\Catalogs\Domain\Subject\SubjectCatalog;
use AulaX\Catalogs\Domain\Subject\SubjectEntry;

/**
 * One subject per level: the smallest valid catalog.
 *
 * @return list<SubjectEntry>
 */
function onePerLevel(): array
{
    return [
        SubjectEntry::of('INI-RAMB', 'Relación con el Ambiente', EducationLevel::Inicial),
        SubjectEntry::of('PRI-LCC', 'Lenguaje, Comunicación y Cultura', EducationLevel::Primaria),
        SubjectEntry::of('MAT', 'Matemáticas', EducationLevel::MediaGeneral),
    ];
}

it('keeps every subject of a valid catalog', function () {
    $catalog = SubjectCatalog::of(...onePerLevel());

    expect($catalog->codes())->toBe(['INI-RAMB', 'PRI-LCC', 'MAT']);
});

it('rejects two subjects with the same code', function () {
    $entries = [...onePerLevel(), SubjectEntry::of('MAT', 'Matemática', EducationLevel::MediaGeneral)];

    expect(fn () => SubjectCatalog::of(...$entries))->toThrow(InvalidCatalog::class, 'MAT');
});

it('rejects a catalog that leaves an education level without subjects', function () {
    $withoutPrimaria = array_values(array_filter(onePerLevel(), fn (SubjectEntry $entry) => $entry->level !== EducationLevel::Primaria));

    expect(fn () => SubjectCatalog::of(...$withoutPrimaria))->toThrow(InvalidCatalog::class, 'primaria');
});

it('rejects a subject without a name', function () {
    expect(fn () => SubjectEntry::of('MAT', '  ', EducationLevel::MediaGeneral))->toThrow(InvalidCatalog::class, 'MAT');
});
