<?php

use AulaX\AcademicStructure\Application\ChangeCapacity\ChangeCapacityHandler;
use AulaX\AcademicStructure\Application\RemoveSection\RemoveSectionHandler;
use AulaX\AcademicStructure\Domain\StructureInUse;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Application\Port\WithdrawalHook;
use AulaX\Students\Application\WithdrawStudent\WithdrawStudentCommand;
use AulaX\Students\Application\WithdrawStudent\WithdrawStudentHandler;
use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\PersonalData;
use AulaX\Students\Domain\Student\Relationship;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentRepository;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Enrollment\FailingWithdrawalHook;

require_once dirname(__DIR__, 3).'/Fixtures/Enrollment/enrollment_helpers.php';

/**
 * SPEC-010 MAT-R05.1, R05.5, R10.1, R10.2 (ADR 0012): the extension points
 * with the real modules, in the same transaction.
 */
beforeEach(function () {
    $institution = tenant();
    $this->actingAs(userWithRole($institution, 'Administrador'));
    $this->year = enrollmentYear();
    $this->section = enrollmentSection($this->year, capacity: 30);

    $guardians = app(GuardianRepository::class);
    $mother = Guardian::register(GuardianData::fromInput('María', 'Mora', 'national_id', 'V15432100', '0414-5550000'));
    $mother->identify($guardians->save($mother));
    $this->student = app(StudentRepository::class)->save(Student::register(
        PersonalData::fromInput('Ana', 'Pérez', 'female', '2018-03-10', 'V', null, null, null, null, null, new DateTimeImmutable('2026-10-21')),
        StudentIdentification::fromInput('national_id', 'V30111222'),
        [new Guardianship($mother->id(), $mother->data()->document, Relationship::Mother, true, true)],
        HealthRecord::fromInput(null, null, null),
    ))->value;
    $this->enrollment = enrollmentRow($this->student, $this->year, $this->section);
});

function withdrawRegistered(): void
{
    app(WithdrawStudentHandler::class)(new WithdrawStudentCommand(test()->student, '2026-10-20', 'moved', null));
}

it('withdrawing a student ends its enrollment on that date, recorded', function () {
    withdrawRegistered();

    expect(DB::table('enrollments')->where('id', $this->enrollment)->first(['ends_on', 'end_reason']))
        ->toEqual((object) ['ends_on' => '2026-10-20', 'end_reason' => 'withdrawal'])
        ->and(DB::table('audit_entries')->where('event_type', 'enrollment.enrollment.ended')->count())->toBe(1);
});

it('if the enrollment can not end, the withdrawal does not happen either', function () {
    app()->tag([FailingWithdrawalHook::class], WithdrawalHook::TAG);

    expect(fn () => withdrawRegistered())->toThrow(RuntimeException::class, 'The reaction to the withdrawal failed.')
        ->and(DB::table('students')->where('id', $this->student)->value('status'))->toBe('active')
        ->and(DB::table('enrollments')->where('id', $this->enrollment)->value('ends_on'))->toBeNull();
});

it('a section with enrollments is not removed, not even once they ended', function () {
    DB::table('enrollments')->update(['ends_on' => '2026-10-01', 'end_reason' => 'withdrawal']);

    expect(fn () => app(RemoveSectionHandler::class)($this->section))->toThrow(StructureInUse::class, 'La sección tiene estudiantes inscritos.')
        ->and(DB::table('sections')->where('id', $this->section)->exists())->toBeTrue();
});

it('the capacity of a section does not drop below its current enrollments', function () {
    enrollmentRow(enrollmentStudent('Álvarez', 'Luis'), $this->year, $this->section);

    expect(fn () => app(ChangeCapacityHandler::class)($this->section, 1, null))
        ->toThrow(BusinessRuleViolation::class, 'La sección tiene 2 estudiantes inscritos: el cupo no puede ser menor.');
});
