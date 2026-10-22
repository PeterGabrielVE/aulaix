<?php

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\StudentStatus;
use AulaX\Students\Domain\Student\Withdrawal;
use AulaX\Students\Domain\Student\WithdrawalReason;

require_once dirname(__DIR__, 3).'/Fixtures/Students/student_helpers.php';

/**
 * SPEC-009 EST-R04.1, R06: health data, withdrawal and reinstatement.
 */
function withdrawal(?string $date = '2026-10-01', ?string $reason = 'moved', ?string $note = null): Withdrawal
{
    return Withdrawal::fromInput($date, $reason, $note, studentsToday());
}

it('keeps optional health data, trimmed', function () {
    $student = aStudent();

    $student->changeHealth(HealthRecord::fromInput(' Penicilina ', null, ''));

    expect($student->health()->allergies)->toBe('Penicilina')
        ->and($student->health()->medicalConditions)->toBeNull()
        ->and($student->health()->isEmpty())->toBeFalse()
        ->and(HealthRecord::fromInput(null, ' ', null)->isEmpty())->toBeTrue();
});

it('limits each health datum to 1000 characters', function () {
    expect(studentViolations(fn () => HealthRecord::fromInput(str_repeat('a', 1001), null, null)))->toHaveKey('allergies');
});

it('withdraws an active student, keeping its record', function () {
    $student = aStudent();

    $student->withdraw(withdrawal(note: 'Se muda a Mérida'));

    expect($student->status())->toBe(StudentStatus::Withdrawn)
        ->and($student->withdrawals()[0]->reason)->toBe(WithdrawalReason::Moved)
        ->and($student->withdrawals()[0]->note)->toBe('Se muda a Mérida')
        ->and($student->personalData()->givenNames)->toBe('Ana Sofía');
});

it('needs the date and the reason of a withdrawal, not in the future', function () {
    expect(array_keys(studentViolations(fn () => withdrawal(null, null))))->toEqualCanonicalizing(['withdrawn_on', 'reason'])
        ->and(studentViolations(fn () => withdrawal('2026-10-22')))->toBe(['withdrawn_on' => ['La fecha del retiro no puede ser posterior a hoy.']]);
});

it('offers the four reasons of the list', function () {
    expect(array_map(fn (WithdrawalReason $reason) => $reason->value, WithdrawalReason::cases()))
        ->toBe(['moved', 'changed_school', 'economic_or_family', 'other'])
        ->and(studentViolations(fn () => withdrawal(reason: 'expulsion')))->toHaveKey('reason');
});

it('does not withdraw a student twice', function () {
    $student = aStudent();
    $student->withdraw(withdrawal());

    expect(fn () => $student->withdraw(withdrawal()))->toThrow(BusinessRuleViolation::class, 'El estudiante ya está retirado.');
});

it('reinstates a withdrawn student, keeping the history of its withdrawals', function () {
    $student = aStudent();
    $student->withdraw(withdrawal());

    $student->reinstate(studentsToday());

    expect($student->status())->toBe(StudentStatus::Active)
        ->and($student->withdrawals())->toHaveCount(1)
        ->and($student->withdrawals()[0]->reinstatedOn->format('Y-m-d'))->toBe('2026-10-21')
        ->and(fn () => $student->reinstate(studentsToday()))->toThrow(BusinessRuleViolation::class, 'El estudiante ya está activo.');
});
