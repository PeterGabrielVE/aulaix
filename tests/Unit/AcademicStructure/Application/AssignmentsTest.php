<?php

use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicStructure\Application\AssignCoordinator\AssignCoordinatorHandler;
use AulaX\AcademicStructure\Application\AssignTeacher\AssignTeacherHandler;
use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\AcademicStructure\Application\RemoveCoordinator\RemoveCoordinatorHandler;
use AulaX\AcademicStructure\Application\SetGuide\SetGuideHandler;
use AulaX\AcademicStructure\Domain\Capacity;
use AulaX\AcademicStructure\Domain\Grade;
use AulaX\AcademicStructure\Domain\SchoolYearStructure;
use AulaX\AcademicStructure\Domain\SectionLetter;
use AulaX\AcademicStructure\Domain\YearStatus;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\AcademicStructure\InMemoryStaffDirectory;
use Tests\Fixtures\AcademicStructure\StructureScene;

/**
 * SPEC-008 EAC-R05, R06, R07.2, R11.1, R12.3: assigning people in a school
 * year, only to those who may take the post.
 */
beforeEach(function () {
    $this->scene = new StructureScene(['primaria']);
    $this->scene->year(2, 2026, SchoolYearStatus::Active);
    $structure = SchoolYearStructure::empty(2, YearStatus::Active, ['primaria']);
    $structure->createSection(Grade::Primaria1, SectionLetter::fromString('A'), Capacity::of(30, null, Grade::Primaria1));
    $structure->setCurriculum(Grade::Primaria1, [1, 2], [1 => 'primaria', 2 => 'primaria'], []);
    $this->scene->structures->save($structure);
    $this->section = $structure->sections()[0]->id();

    $this->staff = new InMemoryStaffDirectory;
    $this->staff->add(50, 'Ana Pérez', ['Docente']);
    $this->staff->add(51, 'Luis Mora', ['Docente']);
    $this->staff->add(60, 'Rosa Díaz', ['Director', 'Docente']);
    $this->staff->add(61, 'Pedro Gil', ['Director']);
    $this->staff->add(70, 'Carla Ruiz', ['Coordinador']);
    $this->staff->add(52, 'Eva Sanz', ['Docente'], active: false);
    $this->canManage = true;
});

function assignmentPermissions(): StructurePermissions
{
    $test = test();

    return new class($test) implements StructurePermissions
    {
        public function __construct(private readonly object $test) {}

        public function canManage(): bool
        {
            return $this->test->canManage;
        }

        public function canView(): bool
        {
            return true;
        }
    };
}

function assignTeacher(int $subject, ?int $account): void
{
    $scene = test()->scene;
    (new AssignTeacherHandler($scene->loader(), $scene->structures, test()->staff, assignmentPermissions(), $scene->lock, $scene->audit, $scene->transactions))(test()->section, $subject, $account);
}

function setGuide(?int $account): void
{
    $scene = test()->scene;
    (new SetGuideHandler($scene->loader(), $scene->structures, test()->staff, assignmentPermissions(), $scene->lock, $scene->audit, $scene->transactions))(test()->section, $account);
}

function assignCoordinator(int $account, string $level, array $grades = []): void
{
    $scene = test()->scene;
    (new AssignCoordinatorHandler($scene->loader(), $scene->structures, test()->staff, assignmentPermissions(), $scene->lock, $scene->audit, $scene->transactions))(2, $account, $level, $grades);
}

function stored(): SchoolYearStructure
{
    return test()->scene->structures->structures[2];
}

it('assigns and replaces the teacher of a subject, recorded with old and new', function () {
    assignTeacher(1, 50);
    assignTeacher(1, 51);

    expect(stored()->section($this->section)->teachers())->toBe([1 => 51])
        ->and($this->scene->audit->types())->toBe(['structure.teacher.assigned', 'structure.teacher.assigned'])
        ->and($this->scene->audit->changesOf(1)['account_id'])->toBe(['from' => 50, 'to' => 51])
        ->and($this->scene->audit->recorded[1]['inTransaction'])->toBeTrue()
        ->and($this->scene->lock->takenInsideTransaction)->toBe(2);
});

it('takes a teacher who is also Director, but not a Director who is not a teacher, nor someone inactive or unknown', function () {
    assignTeacher(2, 60);

    expect(stored()->section($this->section)->teachers())->toBe([2 => 60])
        ->and(fn () => assignTeacher(1, 61))->toThrow(BusinessRuleViolation::class, 'Esa persona no es docente activo del plantel.')
        ->and(fn () => assignTeacher(1, 52))->toThrow(BusinessRuleViolation::class, 'Esa persona no es docente activo del plantel.')
        ->and(fn () => assignTeacher(1, 999))->toThrow(BusinessRuleViolation::class, 'Esa persona no es docente activo del plantel.');
});

it('removes a teacher', function () {
    assignTeacher(1, 50);

    assignTeacher(1, null);

    expect(stored()->section($this->section)->teachers())->toBe([])
        ->and($this->scene->audit->changesOf(1)['account_id'])->toBe(['from' => 50, 'to' => null]);
});

it('names and removes the guide teacher, only a teacher', function () {
    setGuide(50);

    expect(stored()->section($this->section)->guideAccountId())->toBe(50)
        ->and(fn () => setGuide(70))->toThrow(BusinessRuleViolation::class, 'Esa persona no es docente activo del plantel.');

    setGuide(null);

    expect(stored()->section($this->section)->guideAccountId())->toBeNull()
        ->and($this->scene->audit->types())->toBe(['structure.guide.changed', 'structure.guide.changed']);
});

it('assigns and removes coordinators, only coordinators', function () {
    assignCoordinator(70, 'primaria', ['primaria_1', 'primaria_2']);

    expect(stored()->coordinators()[0]->grades)->toBe([Grade::Primaria1, Grade::Primaria2])
        ->and(fn () => assignCoordinator(50, 'primaria'))->toThrow(BusinessRuleViolation::class, 'Esa persona no es coordinador activo del plantel.');

    $scene = $this->scene;
    (new RemoveCoordinatorHandler($scene->loader(), $scene->structures, assignmentPermissions(), $scene->lock, $scene->audit, $scene->transactions))(2, 70, 'primaria');

    expect(stored()->coordinators())->toBe([])
        ->and($this->scene->audit->types())->toBe(['structure.coordinator.assigned', 'structure.coordinator.removed']);
});

it('refuses whoever may not manage the structure', function () {
    $this->canManage = false;

    expect(fn () => assignTeacher(1, 50))->toThrow(AuthorizationDenied::class)
        ->and(fn () => setGuide(50))->toThrow(AuthorizationDenied::class)
        ->and(fn () => assignCoordinator(70, 'primaria'))->toThrow(AuthorizationDenied::class);
});
