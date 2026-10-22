<?php

use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructureRepository;
use AulaX\AcademicStructure\Domain\SectionLetter;
use AulaX\AcademicStructure\Domain\YearStatus;
use AulaX\Shared\Domain\Conflict;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Infrastructure\Persistence\InstitutionModel;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-008: a school year's structure goes to the database and back whole,
 * its sections keeping their ids (SPEC-010 and 011 will point at them).
 */
function structures(): SchoolYearStructureRepository
{
    return app(SchoolYearStructureRepository::class);
}

/**
 * @return array{year: int, math: int, arts: int}
 */
function yearWithSubjects(): array
{
    return [
        'year' => DB::table('school_years')->insertGetId(['starts_on' => '2026-09-16', 'ends_on' => '2027-07-15', 'status' => 'planned']),
        'math' => DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Matemática', 'education_level' => 'primaria']),
        'arts' => DB::table('subjects')->insertGetId(['code' => 'T-'.uniqid(), 'name' => 'Artes', 'education_level' => 'primaria']),
    ];
}

it('stores the structure of a year and reads it back', function () {
    tenant();
    ['year' => $year, 'math' => $math, 'arts' => $arts] = yearWithSubjects();
    $teacher = userWithRole(currentStructureInstitution(), 'Docente');

    $structure = structures()->load($year, YearStatus::Planned, ['primaria']);
    $structure->createSection(Grade::Primaria1, SectionLetter::fromString('A'), Capacity::of(36, 'Alta demanda', Grade::Primaria1));
    $structure->setCurriculum(Grade::Primaria1, [$math, $arts], [$math => 'primaria', $arts => 'primaria'], []);
    structures()->save($structure);
    $id = $structure->sections()[0]->id();
    $structure->section($id)->assignTeacher($math, $teacher->id);
    $structure->section($id)->setGuide($teacher->id);
    structures()->save($structure);

    $stored = structures()->load($year, YearStatus::Planned, ['primaria']);
    $section = $stored->section($id);
    expect($section->letter()->value)->toBe('A')
        ->and($section->capacity()->value)->toBe(36)
        ->and($section->capacity()->reason)->toBe('Alta demanda')
        ->and($section->teachers())->toBe([$math => $teacher->id])
        ->and($section->guideAccountId())->toBe($teacher->id)
        ->and($stored->curriculumOf(Grade::Primaria1))->toBe([$math, $arts]);
});

it('keeps the ids of the sections it already had, and deletes the removed ones', function () {
    tenant();
    ['year' => $year] = yearWithSubjects();
    $structure = structures()->load($year, YearStatus::Planned, ['primaria']);
    $structure->createSection(Grade::Primaria1, SectionLetter::fromString('A'), Capacity::of(30, null, Grade::Primaria1));
    $structure->createSection(Grade::Primaria1, SectionLetter::fromString('B'), Capacity::of(30, null, Grade::Primaria1));
    structures()->save($structure);
    [$first, $second] = array_map(fn ($section) => $section->id(), $structure->sections());

    $structure->changeCapacity($first, Capacity::of(25, null, Grade::Primaria1), 0);
    $structure->removeSection($second, false);
    structures()->save($structure);

    expect(DB::table('sections')->pluck('capacity', 'id')->all())->toBe([$first => 25]);
});

it('turns a broken unique rule into a conflict', function () {
    tenant();
    ['year' => $year] = yearWithSubjects();
    $one = structures()->load($year, YearStatus::Planned, ['primaria']);
    $other = structures()->load($year, YearStatus::Planned, ['primaria']);
    $one->createSection(Grade::Primaria1, SectionLetter::fromString('A'), Capacity::of(30, null, Grade::Primaria1));
    $other->createSection(Grade::Primaria1, SectionLetter::fromString('A'), Capacity::of(30, null, Grade::Primaria1));
    structures()->save($one);

    expect(fn () => DB::transaction(fn () => structures()->save($other)))->toThrow(Conflict::class);
});

it('starts empty for a year with no structure', function () {
    tenant();
    ['year' => $year] = yearWithSubjects();

    expect(structures()->load($year, YearStatus::Active, ['primaria'])->isEmpty())->toBeTrue();
});

function currentStructureInstitution(): InstitutionModel
{
    return InstitutionModel::query()
        ->findOrFail(app(CurrentTenant::class)->institutionId()->value);
}
