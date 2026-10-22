<?php

use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructure;
use AulaX\AcademicStructure\Domain\SectionLetter;
use AulaX\AcademicStructure\Domain\StructureInUse;
use AulaX\AcademicStructure\Domain\YearStatus;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-008 EAC-R01.2, R01.3, R02, R03, R07: the sections and curriculum of a
 * school year, and what each state of the year allows.
 */
function structure(YearStatus $status = YearStatus::Planned, array $levels = ['primaria', 'media_general']): SchoolYearStructure
{
    return SchoolYearStructure::empty(7, $status, $levels);
}

/**
 * Gives every new section of the structure an id, as the repository does.
 */
function persisted(SchoolYearStructure $structure): SchoolYearStructure
{
    $next = max([99, ...array_map(fn ($section) => (int) $section->id(), $structure->sections())]) + 1;

    foreach ($structure->sections() as $section) {
        if ($section->id() === null) {
            $section->identify($next++);
        }
    }

    return $structure;
}

function sectionOf(SchoolYearStructure $structure, Grade $grade = Grade::Primaria1, string $letter = 'A', int $capacity = 30): int
{
    $structure->createSection($grade, SectionLetter::fromString($letter), Capacity::of($capacity, null, $grade));
    persisted($structure);

    return $structure->sections()[array_key_last($structure->sections())]->id();
}

/**
 * Subject 1 and 2 are Primaria's, 9 is Media General's.
 */
function subjectLevels(): array
{
    return [1 => 'primaria', 2 => 'primaria', 9 => 'media_general'];
}

it('creates sections of the offered grades, one letter each', function () {
    $structure = structure();

    $structure->createSection(Grade::Primaria1, SectionLetter::fromString('A'), Capacity::of(30, null, Grade::Primaria1));
    $structure->createSection(Grade::Primaria1, SectionLetter::fromString('B'), Capacity::of(28, null, Grade::Primaria1));
    $structure->createSection(Grade::Primaria2, SectionLetter::fromString('A'), Capacity::of(30, null, Grade::Primaria2));

    expect(array_map(fn ($section) => $section->grade()->value.$section->letter()->value, $structure->sections()))
        ->toBe(['primaria_1A', 'primaria_1B', 'primaria_2A']);
});

it('refuses a letter the grade already has in the year', function () {
    $structure = structure();
    sectionOf($structure);

    expect(fn () => sectionOf($structure))->toThrow(BusinessRuleViolation::class, 'Ya hay una sección 1.er grado A en este año escolar.');
});

it('refuses grades of levels the institution does not offer, or before it says which', function () {
    expect(fn () => sectionOf(structure(levels: ['primaria']), Grade::Media1))->toThrow(BusinessRuleViolation::class, 'Ese grado no es de los niveles del plantel.')
        ->and(fn () => sectionOf(structure(levels: [])))->toThrow(BusinessRuleViolation::class, 'Completa los niveles del plantel antes de crear secciones.');
});

it('changes the capacity, never below the enrolled students', function () {
    $structure = structure(YearStatus::Active);
    $id = sectionOf($structure);

    $structure->changeCapacity($id, Capacity::of(25, null, Grade::Primaria1), enrolled: 20);

    expect($structure->section($id)->capacity()->value)->toBe(25)
        ->and(fn () => $structure->changeCapacity($id, Capacity::of(19, null, Grade::Primaria1), enrolled: 20))
        ->toThrow(BusinessRuleViolation::class, 'La sección tiene 20 estudiantes inscritos: el cupo no puede ser menor.');
});

it('removes a section with its teacher assignments', function () {
    $structure = structure();
    $id = sectionOf($structure);
    $structure->setCurriculum(Grade::Primaria1, [1], subjectLevels(), []);
    $structure->section($id)->assignTeacher(1, 50);

    $removed = $structure->removeSection($id, inUse: false);

    expect($structure->sections())->toBe([])
        ->and($removed->teachers())->toBe([1 => 50]);
});

it('keeps the curriculum of each grade with subjects of its level, each once', function () {
    $structure = structure();

    $structure->setCurriculum(Grade::Primaria3, [2, 1], subjectLevels(), []);

    expect($structure->curriculumOf(Grade::Primaria3))->toBe([2, 1])
        ->and(fn () => $structure->setCurriculum(Grade::Primaria3, [1, 9], subjectLevels(), []))->toThrow(BusinessRuleViolation::class, 'Esa materia no es de este nivel.')
        ->and(fn () => $structure->setCurriculum(Grade::Primaria3, [1, 1], subjectLevels(), []))->toThrow(BusinessRuleViolation::class, 'Una materia aparece dos veces en el pensum.')
        ->and(fn () => $structure->setCurriculum(Grade::Primaria3, [77], subjectLevels(), []))->toThrow(BusinessRuleViolation::class, 'Esa materia no es de este nivel.');
});

it('takes a subject out of the curriculum with its teachers in that grade', function () {
    $structure = structure();
    $first = sectionOf($structure, Grade::Primaria1, 'A');
    $other = sectionOf($structure, Grade::Primaria2, 'A');
    $structure->setCurriculum(Grade::Primaria1, [1, 2], subjectLevels(), []);
    $structure->setCurriculum(Grade::Primaria2, [1], subjectLevels(), []);
    $structure->section($first)->assignTeacher(1, 50);
    $structure->section($first)->assignTeacher(2, 51);
    $structure->section($other)->assignTeacher(1, 50);

    $structure->setCurriculum(Grade::Primaria1, [2], subjectLevels(), []);

    expect($structure->section($first)->teachers())->toBe([2 => 51])
        ->and($structure->section($other)->teachers())->toBe([1 => 50]);
});

it('allows any change while the year is planned, even of what is in use', function () {
    $structure = structure(YearStatus::Planned);
    $id = sectionOf($structure);
    $structure->setCurriculum(Grade::Primaria1, [1], subjectLevels(), []);

    $structure->setCurriculum(Grade::Primaria1, [], subjectLevels(), inUse: [1]);
    $structure->removeSection($id, inUse: true);

    expect($structure->sections())->toBe([]);
});

it('allows creating, adding and changing while the year is active, but not removing what is in use', function () {
    $structure = structure(YearStatus::Active);
    $id = sectionOf($structure);
    $structure->setCurriculum(Grade::Primaria1, [1], subjectLevels(), []);

    $structure->setCurriculum(Grade::Primaria1, [1, 2], subjectLevels(), []);
    sectionOf($structure, Grade::Primaria1, 'B');

    expect($structure->sections())->toHaveCount(2)
        ->and(fn () => $structure->removeSection($id, inUse: true))->toThrow(StructureInUse::class, 'La sección tiene estudiantes inscritos.')
        ->and(fn () => $structure->setCurriculum(Grade::Primaria1, [2], subjectLevels(), inUse: [1]))->toThrow(StructureInUse::class, 'La materia ya tiene notas.');
});

it('changes nothing of a closed year', function () {
    $structure = SchoolYearStructure::reconstitute(7, YearStatus::Closed, ['primaria'], [], [], []);

    expect(fn () => $structure->createSection(Grade::Primaria1, SectionLetter::fromString('A'), Capacity::of(30, null, Grade::Primaria1)))
        ->toThrow(BusinessRuleViolation::class, 'El año escolar está cerrado.')
        ->and(fn () => $structure->setCurriculum(Grade::Primaria1, [1], subjectLevels(), []))
        ->toThrow(BusinessRuleViolation::class, 'El año escolar está cerrado.');
});
