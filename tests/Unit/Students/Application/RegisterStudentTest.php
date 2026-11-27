<?php

use AulaX\Catalogs\Application\Countries;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Application\RegisterStudent\RegisterStudentCommand;
use AulaX\Students\Application\RegisterStudent\RegisterStudentHandler;
use AulaX\Students\Application\StudentForm\GuardianFields;
use AulaX\Students\Application\StudentForm\StudentFields;
use AulaX\Students\Application\StudentForm\StudentForm;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Application\UpdateStudent\UpdateStudentCommand;
use AulaX\Students\Application\UpdateStudent\UpdateStudentHandler;
use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Student\StudentId;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Catalogs\StubGeographyDirectory;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;
use Tests\Fixtures\Students\InMemoryGuardianRepository;
use Tests\Fixtures\Students\InMemoryStudentRepository;
use Tests\Fixtures\Students\SwitchableStudentPermissions;

/**
 * SPEC-009 EST-R01 – R03, R09.1, R10.1: registering and editing a student
 * with its guardians.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->students = new InMemoryStudentRepository;
    $this->guardians = new InMemoryGuardianRepository;
    $this->permissions = new SwitchableStudentPermissions;
});

function studentForm(): StudentForm
{
    $test = test();

    return new StudentForm($test->guardians, $test->students, new Countries, new StubGeographyDirectory, new FixedClock);
}

function newMother(array $fields = []): GuardianFields
{
    return new GuardianFields(...($fields + [
        'guardianId' => null, 'givenNames' => 'María', 'familyNames' => 'Mora', 'documentType' => 'national_id',
        'document' => 'V15432100', 'phone' => '0414-5550000', 'email' => null, 'address' => null,
        'relationship' => 'mother', 'isLegal' => true, 'isPrimary' => true,
    ]));
}

function studentFields(array $fields = []): StudentFields
{
    return new StudentFields(...($fields + [
        'givenNames' => 'Ana', 'familyNames' => 'Pérez', 'sex' => 'female', 'birthDate' => '2019-03-04', 'nationality' => 'V',
        'identificationType' => 'school_id', 'identification' => 'V11915432100', 'birthCountry' => 'VE', 'birthStateId' => 4,
        'parishId' => 10, 'address' => null, 'emergencyPhone' => null, 'guardians' => [newMother()],
    ]));
}

function registerStudent(array $fields = [], ?string $allergies = null): StudentId
{
    $test = test();

    return (new RegisterStudentHandler(studentForm(), $test->students, $test->permissions, $test->audit, $test->transactions))(
        new RegisterStudentCommand(studentFields($fields), $allergies, null, null),
    );
}

function updateStudent(StudentId $id, array $fields = []): void
{
    $test = test();

    (new UpdateStudentHandler(studentForm(), $test->students, $test->permissions, $test->audit, $test->transactions))(
        new UpdateStudentCommand($id->value, studentFields($fields)),
    );
}

/**
 * @return array<string, list<string>>
 */
function registrationErrors(Closure $operation): array
{
    try {
        $operation();
    } catch (BusinessRuleViolations $violations) {
        return $violations->messagesByField();
    }

    test()->fail('The form was accepted.');
}

it('registers a student with a new guardian, recorded inside the transaction', function () {
    $id = registerStudent(allergies: 'Penicilina');

    $student = $this->students->byId($id);
    expect($student->personalData()->givenNames)->toBe('Ana')
        ->and($student->health()->allergies)->toBe('Penicilina')
        ->and($this->guardians->guardians)->toHaveCount(1)
        ->and($student->guardianships()[0]->guardianId->value)->toBe(1)
        ->and($this->audit->types())->toBe(['students.student.registered'])
        ->and($this->audit->recorded[0]['inTransaction'])->toBeTrue();
});

it('links the guardian the institution already has with that document, without overwriting it', function () {
    $existing = Guardian::register(GuardianData::fromInput('María José', 'Mora', 'national_id', 'V15432100', '0212-1112233'));
    $this->guardians->save($existing);

    registerStudent(['guardians' => [newMother(['givenNames' => 'Otro nombre', 'document' => 'V-15.432.100'])]]);

    expect($this->guardians->guardians)->toHaveCount(1)
        ->and($this->guardians->guardians[1]->data()->givenNames)->toBe('María José');
});

it('links an existing guardian by its id', function () {
    $existing = Guardian::register(GuardianData::fromInput('María', 'Mora', 'national_id', 'V15432100', '0414'));
    $this->guardians->save($existing);

    $id = registerStudent(['guardians' => [newMother(['guardianId' => 1, 'givenNames' => null, 'document' => null, 'phone' => null])]]);

    expect($this->students->byId($id)->guardianships()[0]->guardianId->value)->toBe(1);
});

it('reports the form errors by field, each guardian on its own', function () {
    $errors = registrationErrors(fn () => registerStudent([
        'givenNames' => '', 'birthCountry' => 'XX', 'birthStateId' => null, 'parishId' => 99,
        'guardians' => [newMother(['phone' => '', 'relationship' => 'tío'])],
    ]));

    expect(array_keys($errors))->toEqualCanonicalizing(['given_names', 'birth_country', 'parish_id', 'guardians.0.phone', 'guardians.0.relationship']);
});

it('refuses a birth state that is not in the catalog', function () {
    expect(registrationErrors(fn () => registerStudent(['birthStateId' => 99])))
        ->toBe(['birth_state_id' => ['Elige un estado de la lista.']]);
});

it('refuses an identification another student of the institution has, suggesting another birth type for siblings', function () {
    registerStudent();

    expect(registrationErrors(fn () => registerStudent(['givenNames' => 'Luis'])))
        ->toBe(['identification' => ['Ya hay un estudiante con esa identificación en el plantel. Si es un hermano nacido el mismo año, usa otro tipo de parto.']]);
});

it('applies the rules of the record to the guardians', function () {
    expect(registrationErrors(fn () => registerStudent(['guardians' => [newMother(['isLegal' => false])]])))
        ->toBe(['guardians' => ['El estudiante necesita al menos un representante legal.']]);
});

it('edits a student and records only what changed', function () {
    $id = registerStudent();

    updateStudent($id, ['givenNames' => 'Ana Sofía', 'identificationType' => 'national_id', 'identification' => 'V30123456']);

    expect($this->students->byId($id)->previousSchoolIds())->toBe(['V11915432100'])
        ->and($this->audit->types())->toBe(['students.student.registered', 'students.student.updated'])
        ->and(array_keys($this->audit->changesOf(1)))->toEqualCanonicalizing(['given_names', 'identification_type', 'identification']);
});

it('keeps its own identification free when editing', function () {
    $id = registerStudent();

    updateStudent($id, ['givenNames' => 'Ana Sofía']);

    expect($this->students->byId($id)->personalData()->givenNames)->toBe('Ana Sofía');
});

it('answers not found for a student it does not have', function () {
    expect(fn () => updateStudent(StudentId::fromInt(99)))->toThrow(StudentNotFound::class);
});

it('refuses whoever may not manage students', function () {
    $this->permissions->manage = false;

    expect(fn () => registerStudent())->toThrow(AuthorizationDenied::class)
        ->and($this->students->students)->toBe([]);
});
