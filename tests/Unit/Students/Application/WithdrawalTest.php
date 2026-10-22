<?php

use AulaX\Catalogs\Application\Countries;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Application\RegisterStudent\RegisterStudentCommand;
use AulaX\Students\Application\RegisterStudent\RegisterStudentHandler;
use AulaX\Students\Application\ReinstateStudent\ReinstateStudentHandler;
use AulaX\Students\Application\StudentForm\GuardianFields;
use AulaX\Students\Application\StudentForm\StudentFields;
use AulaX\Students\Application\StudentForm\StudentForm;
use AulaX\Students\Application\WithdrawStudent\WithdrawStudentCommand;
use AulaX\Students\Application\WithdrawStudent\WithdrawStudentHandler;
use AulaX\Students\Domain\Student\StudentStatus;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Catalogs\StubGeographyDirectory;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;
use Tests\Fixtures\Students\InMemoryGuardianRepository;
use Tests\Fixtures\Students\InMemoryStudentRepository;
use Tests\Fixtures\Students\SwitchableStudentPermissions;

/**
 * SPEC-009 EST-R06, R10.2: withdrawing and reinstating a student.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->students = new InMemoryStudentRepository;
    $this->permissions = new SwitchableStudentPermissions;
    $this->clock = new FixedClock;
    $form = new StudentForm(new InMemoryGuardianRepository, $this->students, new Countries, new StubGeographyDirectory, $this->clock);
    $this->studentId = (new RegisterStudentHandler($form, $this->students, $this->permissions, $this->audit, $this->transactions))(
        new RegisterStudentCommand(new StudentFields('Ana', 'Pérez', 'female', '2019-03-04', 'V', 'school_id', 'V11915432100', null, null, null, null, null, [
            new GuardianFields(null, 'María', 'Mora', 'national_id', 'V15432100', '0414', null, null, 'mother', true, true),
        ]), null, null, null),
    );
});

function withdrawStudent(?string $date = '2026-10-20', ?string $reason = 'moved', ?string $note = null): void
{
    $test = test();
    (new WithdrawStudentHandler($test->students, $test->permissions, $test->clock, $test->audit, $test->transactions))(
        new WithdrawStudentCommand($test->studentId->value, $date, $reason, $note),
    );
}

function reinstateStudent(): void
{
    $test = test();
    (new ReinstateStudentHandler($test->students, $test->permissions, $test->clock, $test->audit, $test->transactions))($test->studentId->value);
}

it('withdraws a student with its date and reason, recorded', function () {
    withdrawStudent(note: 'Se muda');

    $student = $this->students->byId($this->studentId);
    expect($student->status())->toBe(StudentStatus::Withdrawn)
        ->and($student->withdrawals()[0]->note)->toBe('Se muda')
        ->and($this->audit->types())->toBe(['students.student.registered', 'students.student.withdrawn'])
        ->and($this->audit->changesOf(1))->toBe([
            'status' => ['from' => 'active', 'to' => 'withdrawn'],
            'withdrawn_on' => ['from' => null, 'to' => '2026-10-20'],
            'reason' => ['from' => null, 'to' => 'moved'],
        ]);
});

it('refuses a withdrawal dated after today, by the clock', function () {
    expect(fn () => withdrawStudent('2026-10-22'))->toThrow(BusinessRuleViolations::class);
});

it('reinstates a withdrawn student, recorded', function () {
    withdrawStudent();

    reinstateStudent();

    expect($this->students->byId($this->studentId)->status())->toBe(StudentStatus::Active)
        ->and($this->audit->types())->toBe(['students.student.registered', 'students.student.withdrawn', 'students.student.reinstated'])
        ->and(fn () => reinstateStudent())->toThrow(BusinessRuleViolation::class, 'El estudiante ya está activo.');
});

it('refuses whoever may not manage students', function () {
    $this->permissions->manage = false;

    expect(fn () => withdrawStudent())->toThrow(AuthorizationDenied::class);
});
