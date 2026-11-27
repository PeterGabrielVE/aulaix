<?php

use AulaX\Catalogs\Application\Countries;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Application\ChangeHealth\ChangeHealthCommand;
use AulaX\Students\Application\ChangeHealth\ChangeHealthHandler;
use AulaX\Students\Application\GuardianNotFound;
use AulaX\Students\Application\RegisterStudent\RegisterStudentCommand;
use AulaX\Students\Application\RegisterStudent\RegisterStudentHandler;
use AulaX\Students\Application\StudentForm\GuardianFields;
use AulaX\Students\Application\StudentForm\StudentFields;
use AulaX\Students\Application\StudentForm\StudentForm;
use AulaX\Students\Application\UpdateGuardian\UpdateGuardianCommand;
use AulaX\Students\Application\UpdateGuardian\UpdateGuardianHandler;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Student\StudentId;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Catalogs\StubGeographyDirectory;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;
use Tests\Fixtures\Students\InMemoryGuardianRepository;
use Tests\Fixtures\Students\InMemoryStudentRepository;
use Tests\Fixtures\Students\SwitchableStudentPermissions;

/**
 * SPEC-009 EST-R03.8, R04.1, R10.1, R10.3, NF02: a student's health data and
 * the correction of a guardian.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->students = new InMemoryStudentRepository;
    $this->guardians = new InMemoryGuardianRepository;
    $this->permissions = new SwitchableStudentPermissions;
    $form = new StudentForm($this->guardians, $this->students, new Countries, new StubGeographyDirectory, new FixedClock);
    $register = fn (string $document = 'V15432100', string $identification = 'V11915432100') => (new RegisterStudentHandler($form, $this->students, $this->permissions, $this->audit, $this->transactions))(
        new RegisterStudentCommand(new StudentFields('Ana', 'Pérez', 'female', '2019-03-04', 'V', 'school_id', $identification, null, null, null, null, null, [
            new GuardianFields(null, 'María', 'Mora', 'national_id', $document, '0414', null, null, 'mother', true, true),
        ]), null, null, null),
    );
    $this->studentId = $register();
    $this->register = $register;
});

function changeHealth(StudentId $id, ?string $allergies, ?string $conditions = null, ?string $disability = null): void
{
    $test = test();
    (new ChangeHealthHandler($test->students, $test->permissions, $test->audit, $test->transactions))(new ChangeHealthCommand($id->value, $allergies, $conditions, $disability));
}

function correctGuardian(int $id, array $data = []): void
{
    $test = test();
    (new UpdateGuardianHandler($test->guardians, $test->permissions, $test->audit, $test->transactions))(new UpdateGuardianCommand(...($data + [
        'guardianId' => $id, 'givenNames' => 'María José', 'familyNames' => 'Mora', 'documentType' => 'national_id',
        'document' => 'V15432100', 'phone' => '0424-1112233', 'email' => 'maria@correo.ve', 'address' => null,
    ])));
}

it('changes the health data, recording which ones without their values', function () {
    changeHealth($this->studentId, 'Penicilina', null, 'Visual');

    expect($this->students->byId($this->studentId)->health()->allergies)->toBe('Penicilina')
        ->and($this->audit->types())->toBe(['students.student.registered', 'students.student.health_changed'])
        ->and($this->audit->changesOf(1))->toBe(['allergies' => ['from' => null, 'to' => null], 'disability' => ['from' => null, 'to' => null]])
        ->and($this->audit->recorded[1]['inTransaction'])->toBeTrue()
        ->and(json_encode(array_map(fn (array $entry) => $entry['event'], $this->audit->recorded)))->not->toContain('Penicilina');
});

it('records nothing when the health data did not change', function () {
    changeHealth($this->studentId, null);

    expect($this->audit->types())->toBe(['students.student.registered']);
});

it('corrects a guardian once for every student it represents, recording the old and new values', function () {
    ($this->register)('V15432100', 'V21915432100');

    correctGuardian(1);

    expect($this->guardians->byId(GuardianId::fromInt(1))->data()->phone)->toBe('0424-1112233')
        ->and($this->guardians->guardians)->toHaveCount(1)
        ->and($this->audit->types())->toContain('students.guardian.updated')
        ->and($this->audit->changesOf(2)['phone'])->toBe(['from' => '0414', 'to' => '0424-1112233']);
});

it('refuses a document another guardian of the institution has', function () {
    ($this->register)('V20111222', 'V11920111222');

    try {
        correctGuardian(2, ['document' => 'V15432100']);
    } catch (BusinessRuleViolations $violations) {
        expect($violations->messagesByField())->toBe(['document' => ['Ya hay un representante con ese documento en el plantel.']]);

        return;
    }

    $this->fail('The duplicate document was accepted.');
});

it('answers not found for a guardian it does not have', function () {
    expect(fn () => correctGuardian(99))->toThrow(GuardianNotFound::class);
});

it('refuses whoever may not manage students', function () {
    $this->permissions->manage = false;

    expect(fn () => changeHealth($this->studentId, 'Penicilina'))->toThrow(AuthorizationDenied::class)
        ->and(fn () => correctGuardian(1))->toThrow(AuthorizationDenied::class);
});
