<?php

use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Identity\GuardianDocument;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\Relationship;

require_once dirname(__DIR__, 3).'/Fixtures/Students/student_helpers.php';

/**
 * SPEC-009 EST-R02.4, R02.5, R02.7, R03.4 – R03.7: a student's guardians and
 * the cédula escolar formed with one of them.
 */
function fatherOf(string $cedula = 'V20111222', int $id = 2, bool $legal = false, bool $primary = false): Guardianship
{
    return new Guardianship(GuardianId::fromInt($id), GuardianDocument::fromInput('national_id', $cedula), Relationship::Father, $legal, $primary);
}

function schoolId(string $value): StudentIdentification
{
    return StudentIdentification::fromInput('school_id', $value);
}

it('takes several guardians, telling which ones are legal', function () {
    $student = aStudent(guardianships: [motherOf(), fatherOf(legal: true)]);

    expect(array_map(fn (Guardianship $link) => [$link->relationship, $link->isLegal], $student->guardianships()))
        ->toBe([[Relationship::Mother, true], [Relationship::Father, true]]);
});

it('needs a legal guardian', function () {
    expect(studentViolations(fn () => aStudent(guardianships: [motherOf(legal: false)])))
        ->toBe(['guardians' => ['El estudiante necesita al menos un representante legal.']]);
});

it('needs exactly one primary contact', function (array $guardianships) {
    expect(studentViolations(fn () => aStudent(guardianships: $guardianships)))
        ->toBe(['guardians' => ['Marca un solo representante como contacto principal.']]);
})->with([
    'none' => [fn () => [motherOf(primary: false), fatherOf()]],
    'two' => [fn () => [motherOf(), fatherOf(primary: true)]],
]);

it('needs the year of the cédula escolar to be the birth year', function () {
    expect(studentViolations(fn () => aStudent(schoolId('V12015432100'))))
        ->toBe(['identification' => ['El año de la cédula escolar no coincide con la fecha de nacimiento.']]);
});

it('needs the cédula escolar to be formed with the cédula of one of its guardians', function (string $schoolId) {
    expect(studentViolations(fn () => aStudent(schoolId($schoolId))))
        ->toBe(['identification' => ['La cédula escolar debe formarse con la cédula de uno de sus representantes.']]);
})->with([
    'someone else' => ['V11999999999'],
    'same number, other nationality' => ['E11915432100'],
]);

it('takes a cédula escolar formed with the father, padded to 8 digits', function () {
    $student = aStudent(schoolId('V11909876543'), [motherOf(), fatherOf('V-9.876.543')]);

    expect($student->identification()->value())->toBe('V11909876543');
});

it('does not unlink the guardian whose cédula forms the current cédula escolar', function () {
    $student = aStudent(guardianships: [motherOf(primary: false), fatherOf(legal: true, primary: true)]);

    expect(studentViolations(fn () => $student->update($student->personalData(), $student->identification(), [fatherOf(legal: true, primary: true)])))
        ->toBe(['guardians' => ['No puedes quitar al representante cuya cédula forma la cédula escolar.']]);
});

it('lets another guardian be unlinked', function () {
    $student = aStudent(guardianships: [motherOf(), fatherOf()]);

    $student->update($student->personalData(), $student->identification(), [motherOf()]);

    expect($student->guardianships())->toHaveCount(1);
});

it('keeps its cédula escolar valid when the guardian cédula is corrected later', function () {
    $student = aStudent();

    $student->update($student->personalData(), $student->identification(), [motherOf('V15432101')]);

    expect($student->identification()->value())->toBe('V11915432100');
});

it('moves to its cédula de identidad, keeping the cédula escolar in its history', function () {
    $student = aStudent();

    $student->update($student->personalData(), StudentIdentification::fromInput('national_id', 'V30123456'), $student->guardianships());

    expect($student->identification()->value())->toBe('V30123456')
        ->and($student->previousSchoolIds())->toBe(['V11915432100']);
});

it('does not keep a corrected cédula escolar as history', function () {
    $student = aStudent(guardianships: [motherOf(), fatherOf()]);

    $student->update($student->personalData(), schoolId('V11920111222'), $student->guardianships());

    expect($student->previousSchoolIds())->toBe([]);
});
