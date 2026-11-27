<?php

use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicStructure\Application\ChangeCapacity\ChangeCapacityHandler;
use AulaX\AcademicStructure\Application\CreateSection\CreateSectionCommand;
use AulaX\AcademicStructure\Application\CreateSection\CreateSectionHandler;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\RemoveSection\RemoveSectionHandler;
use AulaX\AcademicStructure\Domain\SectionNotFound;
use AulaX\AcademicStructure\Domain\StructureInUse;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use Tests\Fixtures\AcademicStructure\StructureScene;

/**
 * SPEC-008 EAC-R01.2, R01.3, R02, R07, R10.1, R11.1: the sections of a
 * school year, under the calendar lock and recorded.
 */
beforeEach(function () {
    $this->scene = new StructureScene;
    $this->scene->year(1, 2025, SchoolYearStatus::Closed);
    $this->scene->year(2, 2026, SchoolYearStatus::Active);
    $this->permissions = new class
    {
        public bool $manage = true;
    };
});

function structurePermissions(): StructurePermissions
{
    $test = test();

    return new class($test) implements StructurePermissions
    {
        public function __construct(private readonly object $test) {}

        public function canManage(): bool
        {
            return $this->test->permissions->manage;
        }

        public function canView(): bool
        {
            return true;
        }
    };
}

function createSection(int $year = 2, string $grade = 'primaria_1', string $letter = 'A', mixed $capacity = 30, ?string $reason = null): int
{
    $scene = test()->scene;

    return (new CreateSectionHandler($scene->loader(), $scene->structures, structurePermissions(), $scene->lock, $scene->audit, $scene->transactions))(
        new CreateSectionCommand($year, $grade, $letter, $capacity, $reason),
    );
}

function changeCapacity(int $section, mixed $capacity, ?string $reason = null): void
{
    $scene = test()->scene;
    (new ChangeCapacityHandler($scene->loader(), $scene->structures, $scene->guards(), structurePermissions(), $scene->lock, $scene->audit, $scene->transactions))($section, $capacity, $reason);
}

function removeSection(int $section): void
{
    $scene = test()->scene;
    (new RemoveSectionHandler($scene->loader(), $scene->structures, $scene->guards(), structurePermissions(), $scene->lock, $scene->audit, $scene->transactions))($section);
}

it('creates a section under the calendar lock, recorded inside the transaction', function () {
    $id = createSection(capacity: 38, reason: 'Alta demanda');

    expect($this->scene->structures->structures[2]->section($id)->capacity()->value)->toBe(38)
        ->and($this->scene->lock->takenInsideTransaction)->toBe(1)
        ->and($this->scene->audit->types())->toBe(['structure.section.created'])
        ->and($this->scene->audit->recorded[0]['inTransaction'])->toBeTrue();
});

it('refuses a grade of a level the institution does not offer, and asks for the levels first', function () {
    expect(fn () => createSection(grade: 'preescolar_1', capacity: 20))->toThrow(BusinessRuleViolation::class, 'Ese grado no es de los niveles del plantel.');

    $this->scene = new StructureScene(levels: []);
    $this->scene->year(2, 2026, SchoolYearStatus::Active);

    expect(fn () => createSection())->toThrow(BusinessRuleViolation::class, 'Completa los niveles del plantel antes de crear secciones.');
});

it('refuses an unknown grade or a wrong capacity', function () {
    expect(fn () => createSection(grade: 'primaria_9'))->toThrow(BusinessRuleViolation::class, 'Elige un grado de la lista.')
        ->and(fn () => createSection(capacity: 36))->toThrow(BusinessRuleViolations::class, 'El cupo de Primaria es de hasta 35: indica el motivo para superarlo.');
});

it('changes nothing of a closed year, nor of a year it does not have', function () {
    expect(fn () => createSection(year: 1))->toThrow(BusinessRuleViolation::class, 'El año escolar está cerrado.')
        ->and(fn () => createSection(year: 9))->toThrow(SchoolYearNotFound::class);
});

it('changes the capacity, never below the enrolled students, recorded', function () {
    $id = createSection();
    $this->scene->enrolled[$id] = 28;

    changeCapacity($id, 29);

    expect($this->scene->structures->structures[2]->section($id)->capacity()->value)->toBe(29)
        ->and($this->scene->audit->changesOf(1)['capacity'])->toBe(['from' => 30, 'to' => 29])
        ->and(fn () => changeCapacity($id, 27))->toThrow(BusinessRuleViolation::class, 'La sección tiene 28 estudiantes inscritos: el cupo no puede ser menor.');
});

it('removes a section, unless it has students in the active year', function () {
    $empty = createSection(letter: 'A');
    $full = createSection(letter: 'B');
    $this->scene->enrolled[$full] = 3;

    removeSection($empty);

    expect(fn () => removeSection($full))->toThrow(StructureInUse::class)
        ->and(array_map(fn ($section) => $section->letter()->value, $this->scene->structures->structures[2]->sections()))->toBe(['B'])
        ->and($this->scene->audit->types())->toContain('structure.section.removed');
});

it('does not remove a section with enrollments, even if none is current any more (SPEC-010 MAT-R10.1)', function () {
    $this->scene->year(3, 2027, SchoolYearStatus::Planned);
    $id = createSection(year: 3);
    $this->scene->withEnrollments[] = $id;

    expect(fn () => removeSection($id))->toThrow(StructureInUse::class, 'La sección tiene estudiantes inscritos.');
});

it('answers not found for a section it does not have', function () {
    expect(fn () => removeSection(99))->toThrow(SectionNotFound::class);
});

it('refuses whoever may not manage the structure', function () {
    $this->permissions->manage = false;

    expect(fn () => createSection())->toThrow(AuthorizationDenied::class);
});
