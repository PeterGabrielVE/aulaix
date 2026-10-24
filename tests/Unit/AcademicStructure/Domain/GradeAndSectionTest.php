<?php

use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SectionLetter;
use AulaX\AcademicStructure\Domain\Stage;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;

/**
 * SPEC-008 EAC-R01.1, R02.3, R02.4, R02.8, R02.9: the official grades, the
 * letter of a section and its capacity.
 */
it('has Maternal I to III, Preescolar 1st to 3rd level, Primaria 1st to 6th grade and Media General 1st to 5th year', function () {
    expect(array_map(fn (Grade $grade) => $grade->value, Grade::cases()))->toBe([
        'maternal_1', 'maternal_2', 'maternal_3', 'preescolar_1', 'preescolar_2', 'preescolar_3',
        'primaria_1', 'primaria_2', 'primaria_3', 'primaria_4', 'primaria_5', 'primaria_6',
        'media_1', 'media_2', 'media_3', 'media_4', 'media_5',
    ])->and(Grade::Maternal2->level())->toBe('inicial')
        ->and(Grade::Primaria4->level())->toBe('primaria')
        ->and(Grade::Media1->level())->toBe('media_general')
        ->and(Grade::Preescolar1->name())->toBe('1.er nivel')
        ->and(Grade::Primaria2->name())->toBe('2.º grado')
        ->and(Grade::Media3->name())->toBe('3.er año')
        ->and(Grade::Maternal1->name())->toBe('Maternal I');
});

it('offers only the grades of the given levels', function () {
    expect(array_map(fn (Grade $grade) => $grade->value, Grade::ofLevels(['media_general'])))
        ->toBe(['media_1', 'media_2', 'media_3', 'media_4', 'media_5'])
        ->and(Grade::ofLevels([]))->toBe([]);
});

it('caps the capacity by stage', function (Grade $grade, Stage $stage, int $cap) {
    expect($grade->stage())->toBe($stage)
        ->and($grade->stage()->capacityCap())->toBe($cap);
})->with([
    'maternal' => [Grade::Maternal1, Stage::Maternal, 15],
    'preescolar' => [Grade::Preescolar2, Stage::Preescolar, 25],
    'primaria' => [Grade::Primaria6, Stage::Primaria, 35],
    'media general' => [Grade::Media5, Stage::MediaGeneral, 40],
]);

it('reads a grade from the form, or refuses it', function () {
    expect(Grade::fromInput('primaria_3'))->toBe(Grade::Primaria3)
        ->and(fn () => Grade::fromInput('primaria_7'))->toThrow(BusinessRuleViolation::class, 'Elige un grado de la lista.');
});

it('takes a single letter A to Z as the section', function () {
    expect(SectionLetter::fromString(' b ')->value)->toBe('B')
        ->and(fn () => SectionLetter::fromString('AB'))->toThrow(BusinessRuleViolation::class, 'La sección es una sola letra de la A a la Z.')
        ->and(fn () => SectionLetter::fromString('Ñ'))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => SectionLetter::fromString(''))->toThrow(BusinessRuleViolation::class);
});

it('needs a whole capacity above zero', function (mixed $value) {
    expect(fn () => Capacity::of($value, null, Grade::Primaria1))
        ->toThrow(BusinessRuleViolations::class, 'El cupo debe ser un número entero mayor que cero.');
})->with([0, -3, '2.5', 'diez', null]);

it('keeps a capacity up to the cap without a reason', function () {
    $capacity = Capacity::of(35, 'ignorado', Grade::Primaria1);

    expect($capacity->value)->toBe(35)
        ->and($capacity->reason)->toBeNull()
        ->and($capacity->isOverCap())->toBeFalse();
});

it('needs a reason to go over the cap of the stage, and keeps it', function () {
    expect(fn () => Capacity::of(26, ' ', Grade::Preescolar1))
        ->toThrow(BusinessRuleViolations::class, 'El cupo de Preescolar es de hasta 25: indica el motivo para superarlo.');

    $capacity = Capacity::of(42, ' Alta demanda autorizada por la zona educativa ', Grade::Media1);

    expect($capacity->value)->toBe(42)
        ->and($capacity->reason)->toBe('Alta demanda autorizada por la zona educativa')
        ->and($capacity->isOverCap())->toBeTrue();
});
