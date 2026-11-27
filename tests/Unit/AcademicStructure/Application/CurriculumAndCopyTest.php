<?php

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicStructure\Application\CopyStructure\CopyStructureHandler;
use AulaX\AcademicStructure\Application\CreateSection\CreateSectionCommand;
use AulaX\AcademicStructure\Application\CreateSection\CreateSectionHandler;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\SetCurriculum\SetCurriculumHandler;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\StructureInUse;
use AulaX\Catalogs\Application\Subjects;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\AcademicStructure\StructureScene;

/**
 * SPEC-008 EAC-R03, R04, R07.3, R11.1: the curriculum of each grade, and a
 * planned year that starts from the structure of the one before.
 */
beforeEach(function () {
    $this->scene = new StructureScene(['primaria']);
    $this->scene->year(1, 2025, SchoolYearStatus::Active);
    $this->scene->year(2, 2026, SchoolYearStatus::Planned);
});

function allowAll(): StructurePermissions
{
    return new class implements StructurePermissions
    {
        public function canManage(): bool
        {
            return true;
        }

        public function canView(): bool
        {
            return true;
        }
    };
}

/**
 * Subjects 1 and 2 of Primaria, 9 of Media General.
 */
function catalogSubjects(): Subjects
{
    return new class implements Subjects
    {
        private const array LEVELS = [1 => 'primaria', 2 => 'primaria', 9 => 'media_general'];

        public function ofLevel(string $level): array
        {
            return [];
        }

        public function levelsOf(array $subjectIds): array
        {
            return array_intersect_key(self::LEVELS, array_flip($subjectIds));
        }
    };
}

function setCurriculum(int $year, string $grade, array $subjects): void
{
    $scene = test()->scene;
    (new SetCurriculumHandler($scene->loader(), $scene->structures, catalogSubjects(), $scene->guards(), allowAll(), $scene->lock, $scene->audit, $scene->transactions))($year, $grade, $subjects);
}

function copyStructure(int $to, ?int $from = null): void
{
    $scene = test()->scene;
    (new CopyStructureHandler($scene->loader(), $scene->structures, new SchoolYearLookup($scene->years), allowAll(), $scene->lock, $scene->audit, $scene->transactions))($to, $from);
}

function sectionIn(int $year, string $letter): int
{
    $scene = test()->scene;

    return (new CreateSectionHandler($scene->loader(), $scene->structures, allowAll(), $scene->lock, $scene->audit, $scene->transactions))(
        new CreateSectionCommand($year, 'primaria_1', $letter, 30, null),
    );
}

it('keeps the curriculum of a grade with subjects of the catalog of its level, recorded', function () {
    setCurriculum(1, 'primaria_1', [1, 2]);

    expect($this->scene->structures->structures[1]->curriculumOf(Grade::Primaria1))->toBe([1, 2])
        ->and($this->scene->audit->types())->toBe(['structure.curriculum.changed'])
        ->and($this->scene->lock->takenInsideTransaction)->toBe(1)
        ->and(fn () => setCurriculum(1, 'primaria_1', [9]))->toThrow(BusinessRuleViolation::class, 'Esa materia no es de este nivel.')
        ->and(fn () => setCurriculum(1, 'primaria_1', [404]))->toThrow(BusinessRuleViolation::class, 'Esa materia no es de este nivel.');
});

it('does not take out of the active year a subject that has grades', function () {
    setCurriculum(1, 'primaria_1', [1, 2]);
    $this->scene->graded = [1];

    expect(fn () => setCurriculum(1, 'primaria_1', [2]))->toThrow(StructureInUse::class, 'La materia ya tiene notas.');

    setCurriculum(1, 'primaria_1', [1]);

    expect($this->scene->structures->structures[1]->curriculumOf(Grade::Primaria1))->toBe([1]);
});

it('copies the structure of the year before into a planned year, recorded', function () {
    sectionIn(1, 'A');
    sectionIn(1, 'B');
    setCurriculum(1, 'primaria_1', [1, 2]);

    copyStructure(2);

    $copy = $this->scene->structures->structures[2];
    expect(array_map(fn ($section) => $section->letter()->value, $copy->sections()))->toBe(['A', 'B'])
        ->and($copy->curriculumOf(Grade::Primaria1))->toBe([1, 2])
        ->and($this->scene->audit->types())->toContain('structure.year.copied');
});

it('needs a year to copy from', function () {
    $this->scene->years->years = [];
    $this->scene->year(2, 2026, SchoolYearStatus::Planned);

    expect(fn () => copyStructure(2))->toThrow(BusinessRuleViolation::class, 'No hay un año escolar anterior del que copiar.');
});
