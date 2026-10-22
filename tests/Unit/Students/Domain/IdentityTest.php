<?php

use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Students\Domain\Identity\GuardianDocument;
use AulaX\Students\Domain\Identity\NationalId;
use AulaX\Students\Domain\Identity\Passport;
use AulaX\Students\Domain\Identity\SchoolId;
use AulaX\Students\Domain\Identity\StudentIdentification;

/**
 * SPEC-009 EST-R02.1 – R02.3, R03.2: the official identifications of
 * students and guardians.
 */
it('reads a cédula de identidad with or without dots and dashes', function (string $typed, string $value, string $padded) {
    $id = NationalId::fromString($typed);

    expect($id->value())->toBe($value)
        ->and($id->paddedNumber())->toBe($padded);
})->with([
    'plain' => ['V15432100', 'V15432100', '15432100'],
    'dotted' => ['v-9.876.543', 'V9876543', '09876543'],
    'foreign' => ['E 81234567', 'E81234567', '81234567'],
    'short' => ['V1', 'V1', '00000001'],
]);

it('rejects a cédula that is not V or E and 1 to 8 digits', function (string $typed) {
    expect(fn () => NationalId::fromString($typed))
        ->toThrow(BusinessRuleViolation::class, 'La cédula debe ser V o E seguida de 1 a 8 dígitos.');
})->with(['', 'V', 'X12345678', 'V123456789', 'V12A45678', 'V0', '12345678']);

it('reads the parts of a cédula escolar', function (string $typed, string $nationality, int $birthType, string $year, string $embedded) {
    $schoolId = SchoolId::fromString($typed);

    expect($schoolId->nationality())->toBe($nationality)
        ->and($schoolId->birthType())->toBe($birthType)
        ->and($schoolId->birthYearSuffix())->toBe($year)
        ->and($schoolId->embeddedNationalId()->value())->toBe($embedded);
})->with([
    'single birth' => ['V11915432100', 'V', 1, '19', 'V15432100'],
    'second twin, padded mother id' => ['V32109876543', 'V', 3, '21', 'V9876543'],
    'typed with dashes' => ['e-4-18-81234567', 'E', 4, '18', 'E81234567'],
]);

it('rejects a cédula escolar that is not N T AA CCCCCCCC', function (string $typed) {
    expect(fn () => SchoolId::fromString($typed))->toThrow(BusinessRuleViolation::class);
})->with([
    'eleven characters' => ['V1191543210'],
    'thirteen characters' => ['V119154321000'],
    'unknown nationality' => ['X11915432100'],
    'birth type 0' => ['V01915432100'],
    'birth type 5' => ['V51915432100'],
    'letters in the year' => ['V1AB15432100'],
    'letters in the cédula' => ['V119154321OO'],
    'no cédula embedded' => ['V11900000000'],
]);

it('identifies a student by one or the other', function () {
    $national = StudentIdentification::fromInput('national_id', 'V-30.123.456');
    $school = StudentIdentification::fromInput('school_id', 'V11915432100');

    expect($national->value())->toBe('V30123456')
        ->and($national->schoolId())->toBeNull()
        ->and($school->value())->toBe('V11915432100')
        ->and($school->schoolId()->birthYearSuffix())->toBe('19')
        ->and($school->equals(StudentIdentification::fromInput('school_id', 'v-1-19-15432100')))->toBeTrue();
});

it('reports a wrong identification on its field', function () {
    try {
        StudentIdentification::fromInput('school_id', 'V1');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->field())->toBe('identification');

        return;
    }

    $this->fail('The identification was accepted.');
});

it('identifies a guardian by cédula or, if foreign without one, by passport', function () {
    $byCedula = GuardianDocument::fromInput('national_id', 'V-15.432.100');
    $byPassport = GuardianDocument::fromInput('passport', 'ab 123456');

    expect($byCedula->value())->toBe('V15432100')
        ->and($byCedula->nationalId()->paddedNumber())->toBe('15432100')
        ->and($byPassport->value())->toBe('AB123456')
        ->and($byPassport->nationalId())->toBeNull();
});

it('rejects a passport that is not 5 to 20 letters or digits', function (string $typed) {
    expect(fn () => Passport::fromString($typed))->toThrow(BusinessRuleViolation::class, 'El pasaporte debe tener entre 5 y 20 letras o dígitos.');
})->with(['', 'AB12', str_repeat('A', 21), 'AB-12*34']);
