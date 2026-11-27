<?php

use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentStatus;

require_once dirname(__DIR__, 3).'/Fixtures/Students/student_helpers.php';

/**
 * SPEC-009 EST-R01: a student's record and its required data.
 */
it('registers an active student with its data, identification and guardians', function () {
    $student = aStudent();

    expect($student->status())->toBe(StudentStatus::Active)
        ->and($student->personalData()->givenNames)->toBe('Ana Sofía')
        ->and($student->identification()->value())->toBe('V11915432100')
        ->and($student->guardianships())->toHaveCount(1)
        ->and($student->id())->toBeNull();
});

it('reports every missing required datum at once', function () {
    $errors = studentViolations(fn () => personalData([
        'givenNames' => ' ', 'familyNames' => '', 'sex' => '', 'birthDate' => '', 'nationality' => '',
    ]));

    expect(array_keys($errors))->toEqualCanonicalizing(['given_names', 'family_names', 'sex', 'birth_date', 'nationality']);
});

it('refuses a birth date after today', function () {
    expect(studentViolations(fn () => personalData(['birthDate' => '2026-10-22'])))
        ->toBe(['birth_date' => ['La fecha de nacimiento no puede ser posterior a hoy.']]);
});

it('keeps the optional data, or none', function () {
    $full = personalData(['birthCountry' => 've', 'birthStateId' => 4, 'parishId' => 10, 'address' => ' Calle 5 ', 'emergencyPhone' => '0414-5550000']);
    $bare = personalData(['address' => ' ', 'emergencyPhone' => '']);

    expect($full->birthCountry)->toBe('VE')
        ->and($full->birthStateId)->toBe(4)
        ->and($full->address)->toBe('Calle 5')
        ->and($bare->address)->toBeNull()
        ->and($bare->emergencyPhone)->toBeNull();
});

it('only takes a birth state for someone born in Venezuela', function () {
    expect(studentViolations(fn () => personalData(['birthCountry' => 'CO', 'birthStateId' => 4])))
        ->toBe(['birth_state_id' => ['El estado de nacimiento solo aplica a quien nació en Venezuela.']]);
});

it('updates its data with the same rules', function () {
    $student = aStudent();

    $student->update(personalData(['givenNames' => 'Ana']), $student->identification(), $student->guardianships());

    expect($student->personalData()->givenNames)->toBe('Ana')
        ->and(studentViolations(fn () => $student->update(personalData(), $student->identification(), [])))
        ->toHaveKey('guardians');
});
