<?php

use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructure;
use AulaX\AcademicStructure\Domain\SectionLetter;
use AulaX\AcademicStructure\Domain\YearStatus;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-008 EAC-R04: a planned year starts from the structure of another one.
 */
function lastYear(): SchoolYearStructure
{
    $structure = SchoolYearStructure::empty(6, YearStatus::Planned, ['primaria', 'media_general']);
    $structure->createSection(Grade::Primaria1, SectionLetter::fromString('A'), Capacity::of(30, null, Grade::Primaria1));
    $structure->createSection(Grade::Media1, SectionLetter::fromString('B'), Capacity::of(42, 'Alta demanda', Grade::Media1));
    $structure->setCurriculum(Grade::Primaria1, [1, 2], [1 => 'primaria', 2 => 'primaria'], []);
    $structure->setCurriculum(Grade::Media1, [9], [9 => 'media_general'], []);
    $structure->sections()[0]->identify(10);
    $structure->sections()[1]->identify(11);
    $structure->sections()[0]->assignTeacher(1, 50);
    $structure->sections()[0]->setGuide(51);

    return $structure;
}

it('copies the sections and the curriculum, without the people', function () {
    $next = SchoolYearStructure::empty(7, YearStatus::Planned, ['primaria', 'media_general']);

    $next->copyFrom(lastYear());

    expect(array_map(fn ($section) => [$section->grade()->value, $section->letter()->value, $section->capacity()->value, $section->capacity()->reason, $section->id(), $section->teachers(), $section->guideAccountId()], $next->sections()))
        ->toBe([
            ['primaria_1', 'A', 30, null, null, [], null],
            ['media_1', 'B', 42, 'Alta demanda', null, [], null],
        ])
        ->and($next->curriculumOf(Grade::Primaria1))->toBe([1, 2])
        ->and($next->curriculumOf(Grade::Media1))->toBe([9]);
});

it('copies only the grades the institution offers now', function () {
    $next = SchoolYearStructure::empty(7, YearStatus::Planned, ['primaria']);

    $next->copyFrom(lastYear());

    expect(array_map(fn ($section) => $section->grade()->value, $next->sections()))->toBe(['primaria_1'])
        ->and($next->curriculumOf(Grade::Media1))->toBe([]);
});

it('copies only into a planned year with no structure yet', function () {
    $active = SchoolYearStructure::empty(7, YearStatus::Active, ['primaria']);
    $filled = SchoolYearStructure::empty(7, YearStatus::Planned, ['primaria']);
    $filled->setCurriculum(Grade::Primaria2, [1], [1 => 'primaria'], []);

    expect(fn () => $active->copyFrom(lastYear()))->toThrow(BusinessRuleViolation::class, 'Solo se copia la estructura a un año escolar planificado.')
        ->and(fn () => $filled->copyFrom(lastYear()))->toThrow(BusinessRuleViolation::class, 'El año destino ya tiene estructura.');
});
