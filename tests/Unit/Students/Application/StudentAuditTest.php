<?php

use AulaX\Audit\Application\FieldChange;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Identity\GuardianDocument;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\PersonalData;
use AulaX\Students\Domain\Student\Relationship;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentId;

/**
 * SPEC-009 EST-R10, NF02: what the audit log keeps of a student.
 */
function auditedChanges(array $changes): array
{
    return array_combine(
        array_map(fn (FieldChange $change) => $change->field, $changes),
        array_map(fn (FieldChange $change) => [$change->from, $change->to], $changes),
    );
}

function auditedStudent(): Student
{
    $student = Student::register(
        PersonalData::fromInput('Ana', 'Pérez', 'female', '2019-03-04', 'V', null, null, null, null, null, new DateTimeImmutable('2026-10-21')),
        StudentIdentification::fromInput('school_id', 'V11915432100'),
        [new Guardianship(GuardianId::fromInt(1), GuardianDocument::fromInput('national_id', 'V15432100'), Relationship::Mother, true, true)],
        HealthRecord::none(),
    );
    $student->identify(StudentId::fromInt(7));

    return $student;
}

it('records a registration with its data and guardians, never its health', function () {
    $student = auditedStudent();
    $student->changeHealth(HealthRecord::fromInput('Penicilina', null, null));

    $event = StudentAudit::registered($student);
    $changes = auditedChanges($event->changes);

    expect($event->type)->toBe('students.student.registered')
        ->and($event->subject->id)->toBe('7')
        ->and($changes['identification'])->toBe([null, 'V11915432100'])
        ->and($changes['guardians'])->toBe([null, ['1:mother:legal:primary']])
        ->and(json_encode($event))->not->toContain('Penicilina')
        ->and($changes)->not->toHaveKey('allergies');
});

it('records which health data changed, without their values', function () {
    $student = auditedStudent();

    $event = StudentAudit::healthChanged(HealthRecord::none(), HealthRecord::fromInput('Penicilina', null, 'Visual'), $student);

    expect($event->type)->toBe('students.student.health_changed')
        ->and(auditedChanges($event->changes))->toBe(['allergies' => [null, null], 'disability' => [null, null]])
        ->and(json_encode($event))->not->toContain('Penicilina');
});
