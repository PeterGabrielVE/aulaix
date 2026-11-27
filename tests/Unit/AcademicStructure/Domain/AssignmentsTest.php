<?php

use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\CoordinatorAssignment;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructure;
use AulaX\AcademicStructure\Domain\SectionLetter;
use AulaX\AcademicStructure\Domain\YearStatus;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-008 EAC-R05, R06: who teaches each subject of a section, who guides
 * it, and who coordinates each level.
 */
function staffedStructure(YearStatus $status = YearStatus::Active): SchoolYearStructure
{
    $structure = SchoolYearStructure::empty(7, $status, ['inicial', 'primaria']);
    $structure->createSection(Grade::Primaria1, SectionLetter::fromString('A'), Capacity::of(30, null, Grade::Primaria1));
    $structure->createSection(Grade::Preescolar2, SectionLetter::fromString('A'), Capacity::of(20, null, Grade::Preescolar2));
    $structure->setCurriculum(Grade::Primaria1, [1, 2], [1 => 'primaria', 2 => 'primaria'], []);
    $structure->setCurriculum(Grade::Preescolar2, [5], [5 => 'inicial'], []);
    $structure->sections()[0]->identify(10);
    $structure->sections()[1]->identify(11);

    return $structure;
}

it('assigns a teacher to a subject of the curriculum in a section, one per subject', function () {
    $structure = staffedStructure();

    $structure->assignTeacher(10, 1, 50);
    $structure->assignTeacher(10, 2, 51);
    $structure->assignTeacher(10, 1, 52);

    expect($structure->section(10)->teachers())->toBe([1 => 52, 2 => 51]);
});

it('assigns specialists in Inicial too, besides the guide', function () {
    $structure = staffedStructure();

    $structure->setGuide(11, 60);
    $structure->assignTeacher(11, 5, 61);

    expect($structure->section(11)->guideAccountId())->toBe(60)
        ->and($structure->section(11)->teachers())->toBe([5 => 61]);
});

it('refuses a subject that is not in the curriculum of the section grade', function () {
    expect(fn () => staffedStructure()->assignTeacher(10, 5, 50))
        ->toThrow(BusinessRuleViolation::class, 'La materia no está en el pensum del grado.');
});

it('removes a teacher, leaving the subject without one', function () {
    $structure = staffedStructure();
    $structure->assignTeacher(10, 1, 50);

    $structure->removeTeacher(10, 1);

    expect($structure->section(10)->teachers())->toBe([]);
});

it('names, replaces and removes the guide of a section', function () {
    $structure = staffedStructure();

    $structure->setGuide(10, 50);
    $structure->setGuide(10, 51);

    expect($structure->section(10)->guideAccountId())->toBe(51);

    $structure->setGuide(10, null);

    expect($structure->section(10)->guideAccountId())->toBeNull();
});

it('assigns coordinators to a whole level or to some of its grades, several per level', function () {
    $structure = staffedStructure();

    $structure->assignCoordinator(new CoordinatorAssignment(70, 'primaria'));
    $structure->assignCoordinator(new CoordinatorAssignment(71, 'primaria', [Grade::Primaria4, Grade::Primaria5]));

    expect(array_map(fn (CoordinatorAssignment $coordinator) => [$coordinator->accountId, $coordinator->level, array_map(fn (Grade $grade) => $grade->value, $coordinator->grades)], $structure->coordinators()))
        ->toBe([[70, 'primaria', []], [71, 'primaria', ['primaria_4', 'primaria_5']]]);
});

it('replaces what a person coordinates in a level, and removes it', function () {
    $structure = staffedStructure();
    $structure->assignCoordinator(new CoordinatorAssignment(70, 'primaria'));

    $structure->assignCoordinator(new CoordinatorAssignment(70, 'primaria', [Grade::Primaria1]));

    expect($structure->coordinators())->toHaveCount(1)
        ->and($structure->coordinators()[0]->grades)->toBe([Grade::Primaria1]);

    $structure->removeCoordinator(70, 'primaria');

    expect($structure->coordinators())->toBe([]);
});

it('refuses a level the institution does not offer, or grades of another level', function () {
    expect(fn () => staffedStructure()->assignCoordinator(new CoordinatorAssignment(70, 'media_general')))
        ->toThrow(BusinessRuleViolation::class, 'Ese nivel no es del plantel.')
        ->and(fn () => staffedStructure()->assignCoordinator(new CoordinatorAssignment(70, 'primaria', [Grade::Preescolar1])))
        ->toThrow(BusinessRuleViolation::class, 'Esos grados no son de ese nivel.');
});

it('changes no assignment of a closed year', function () {
    $structure = SchoolYearStructure::reconstitute(7, YearStatus::Closed, ['primaria'], [], [], []);

    expect(fn () => $structure->assignCoordinator(new CoordinatorAssignment(70, 'primaria')))
        ->toThrow(BusinessRuleViolation::class, 'El año escolar está cerrado.');
});
