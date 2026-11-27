<?php

use AulaX\Students\Application\Enrollable\EnrollableStudent;
use AulaX\Students\Application\Enrollable\EnrollableStudentsQuery;
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
use AulaX\Students\Domain\Student\Withdrawal;
use AulaX\Students\Domain\Student\WithdrawalReason;

/**
 * SPEC-010 (design): what enrolling needs to know of a student, and the
 * active students of the institution, in Spanish order.
 */
function enrollableStudent(string $givenNames, string $familyNames, string $nationalId, string $birthDate = '2018-05-20', bool $withdrawn = false): int
{
    $guardians = app(GuardianRepository::class);
    $mother = Guardian::register(GuardianData::fromInput('María', $familyNames, 'national_id', 'V'.(15000000 + random_int(1, 999999)), '0414-5550000'));
    $mother->identify($guardians->save($mother));

    $student = Student::register(
        PersonalData::fromInput($givenNames, $familyNames, 'female', $birthDate, 'V', null, null, null, null, null, new DateTimeImmutable('2026-10-21')),
        StudentIdentification::fromInput('national_id', $nationalId),
        [new Guardianship($mother->id(), $mother->data()->document, Relationship::Mother, true, true)],
        HealthRecord::fromInput('Penicilina', null, null),
    );

    if ($withdrawn) {
        $student->withdraw(new Withdrawal(new DateTimeImmutable('2026-10-01'), WithdrawalReason::Moved, null));
    }

    return app(StudentRepository::class)->save($student)->value;
}

it('tells the state, birth date, name and identification of the given students', function () {
    tenant();
    $ana = enrollableStudent('Ana', 'Pérez', 'V30111222');
    $luis = enrollableStudent('Luis', 'Álvarez', 'V30333444', '2017-01-31', withdrawn: true);

    $students = app(EnrollableStudentsQuery::class)->byIds([$ana, $luis, 999]);

    expect($students)->toEqual([
        $luis => new EnrollableStudent($luis, 'Álvarez, Luis', 'V30333444', '2017-01-31', false),
        $ana => new EnrollableStudent($ana, 'Pérez, Ana', 'V30111222', '2018-05-20', true),
    ]);
});

it('lists the active students of the institution only, in Spanish order', function () {
    tenant();
    enrollableStudent('Ana', 'Pérez', 'V30111222');
    $luis = enrollableStudent('Luis', 'Álvarez', 'V30333444');
    enrollableStudent('Eva', 'Ñañez', 'V30555666', withdrawn: true);

    expect(array_map(fn (EnrollableStudent $student) => $student->fullName, app(EnrollableStudentsQuery::class)->active()))
        ->toBe(['Álvarez, Luis', 'Pérez, Ana'])
        ->and(app(EnrollableStudentsQuery::class)->active()[0]->id)->toBe($luis);
});

it('sees nothing of another institution', function () {
    tenant();
    $ana = enrollableStudent('Ana', 'Pérez', 'V30111222');
    tenant();

    expect(app(EnrollableStudentsQuery::class)->byIds([$ana]))->toBe([])
        ->and(app(EnrollableStudentsQuery::class)->active())->toBe([]);
});
