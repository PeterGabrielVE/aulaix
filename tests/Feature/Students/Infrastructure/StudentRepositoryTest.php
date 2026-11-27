<?php

use AulaX\Shared\Domain\Conflict;
use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Identity\GuardianDocument;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\PersonalData;
use AulaX\Students\Domain\Student\Relationship;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Student\StudentStatus;
use AulaX\Students\Domain\Student\Withdrawal;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-009: a student goes to the database and back whole: its data, its
 * guardians with their documents, its withdrawals and former cédulas.
 */
function students(): StudentRepository
{
    return app(StudentRepository::class);
}

function guardians(): GuardianRepository
{
    return app(GuardianRepository::class);
}

function savedGuardian(string $cedula = 'V15432100'): Guardian
{
    $guardian = Guardian::register(GuardianData::fromInput('María', 'Mora', 'national_id', $cedula, '0414-5550000'));
    $guardian->identify(guardians()->save($guardian));

    return $guardian;
}

function linkTo(Guardian $guardian, bool $primary = true, Relationship $relationship = Relationship::Mother): Guardianship
{
    return new Guardianship($guardian->id(), $guardian->data()->document, $relationship, true, $primary);
}

function newStudent(Guardian $guardian, string $identification = 'V11915432100'): Student
{
    return Student::register(
        PersonalData::fromInput('Ana', 'Pérez', 'female', '2019-03-04', 'V', 'VE', null, null, 'Calle 5', null, new DateTimeImmutable('2026-10-21')),
        StudentIdentification::fromInput('school_id', $identification),
        [linkTo($guardian)],
        HealthRecord::fromInput('Penicilina', null, null),
    );
}

it('stores a student and reads it back whole', function () {
    tenant();
    $mother = savedGuardian();
    $father = savedGuardian('V20111222');
    $student = newStudent($mother);
    $id = students()->save($student);
    $student->identify($id);

    $student->update($student->personalData(), StudentIdentification::fromInput('national_id', 'V30123456'), [linkTo($mother, primary: false), linkTo($father, relationship: Relationship::Father)]);
    $student->withdraw(Withdrawal::fromInput('2026-10-01', 'moved', 'Mérida', new DateTimeImmutable('2026-10-21')));
    students()->save($student);

    $stored = students()->byId($id);
    expect($stored->identification()->value())->toBe('V30123456')
        ->and($stored->previousSchoolIds())->toBe(['V11915432100'])
        ->and($stored->personalData()->birthCountry)->toBe('VE')
        ->and($stored->health()->allergies)->toBe('Penicilina')
        ->and($stored->status())->toBe(StudentStatus::Withdrawn)
        ->and($stored->withdrawals()[0]->note)->toBe('Mérida')
        ->and(array_map(fn (Guardianship $link) => [$link->guardianId->value, $link->document->value(), $link->isPrimary], $stored->guardianships()))
        ->toEqualCanonicalizing([[$mother->id()->value, 'V15432100', false], [$father->id()->value, 'V20111222', true]]);
});

it('tells whether another student of the institution has an identification', function () {
    tenant();
    $id = students()->save(newStudent(savedGuardian()));
    $taken = StudentIdentification::fromInput('school_id', 'V11915432100');

    expect(students()->isIdentificationTaken($taken, null))->toBeTrue()
        ->and(students()->isIdentificationTaken($taken, $id))->toBeFalse()
        ->and(students()->isIdentificationTaken(StudentIdentification::fromInput('national_id', 'V1'), null))->toBeFalse();
});

it('finds a guardian of the institution by its document, and corrects it', function () {
    tenant();
    $guardian = savedGuardian();

    $found = guardians()->byDocument(GuardianDocument::fromInput('national_id', 'V-15.432.100'));
    $found->correct(GuardianData::fromInput('María José', 'Mora', 'national_id', 'V15432100', '0424'));
    guardians()->save($found);

    expect($found->id()->value)->toBe($guardian->id()->value)
        ->and(guardians()->byId($guardian->id())->data()->givenNames)->toBe('María José')
        ->and(guardians()->byDocument(GuardianDocument::fromInput('passport', 'AB12345')))->toBeNull();
});

it('turns a broken unique rule into a conflict', function () {
    tenant();
    $guardian = savedGuardian();
    students()->save(newStudent($guardian));

    expect(fn () => DB::transaction(fn () => students()->save(newStudent($guardian))))->toThrow(Conflict::class)
        ->and(fn () => DB::transaction(fn () => savedGuardian()))->toThrow(Conflict::class);
});

it('does not read a student of another institution', function () {
    tenant();
    $id = students()->save(newStudent(savedGuardian()));
    tenant();

    expect(students()->byId($id))->toBeNull()
        ->and(guardians()->byId(GuardianId::fromInt(1)))->toBeNull();
});
