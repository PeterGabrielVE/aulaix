<?php

use AulaX\Catalogs\Application\Countries;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\Conflict;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Application\RegisterStudent\RegisterStudentCommand;
use AulaX\Students\Application\RegisterStudent\RegisterStudentHandler;
use AulaX\Students\Application\StudentForm\GuardianFields;
use AulaX\Students\Application\StudentForm\StudentFields;
use AulaX\Students\Application\StudentForm\StudentForm;
use AulaX\Students\Application\Transfer\ApproveTransferHandler;
use AulaX\Students\Application\Transfer\RejectTransferHandler;
use AulaX\Students\Application\Transfer\RequestTransferHandler;
use AulaX\Students\Application\Transfer\TransferNotFound;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\StudentStatus;
use AulaX\Students\Domain\Student\WithdrawalReason;
use AulaX\Students\Domain\Transfer\TransferRequest;
use AulaX\Students\Domain\Transfer\TransferRequestId;
use AulaX\Students\Domain\Transfer\TransferStatus;
use AulaX\Tenancy\Application\TenantContext;
use AulaX\Tenancy\Infrastructure\InMemoryCurrentTenant;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Catalogs\StubGeographyDirectory;
use Tests\Fixtures\Shared\FixedClock;
use Tests\Fixtures\Shared\InlineTransactionManager;
use Tests\Fixtures\Students\InMemoryGuardianRepository;
use Tests\Fixtures\Students\InMemoryStudentRepository;
use Tests\Fixtures\Students\InMemoryTransferRequestRepository;
use Tests\Fixtures\Students\RecordingTransferCopier;
use Tests\Fixtures\Students\SwitchableStudentPermissions;

/**
 * SPEC-009 EST-R07, R10.2: asking for a student, and approving or rejecting
 * the request from the institution that has it.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->students = new InMemoryStudentRepository;
    $this->guardians = new InMemoryGuardianRepository;
    $this->transfers = new InMemoryTransferRequestRepository;
    $this->copier = new RecordingTransferCopier;
    $this->permissions = new SwitchableStudentPermissions;
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-10 09:00'));
    $this->tenant = new InMemoryCurrentTenant;
    $this->tenant->set(new TenantContext(InstitutionId::fromInt(4), 'Colegio Norte', 'norte'));

    // The origin (4) has the student active.
    $form = new StudentForm($this->guardians, $this->students, new Countries, new StubGeographyDirectory, $this->clock);
    $this->studentId = (new RegisterStudentHandler($form, $this->students, $this->permissions, $this->audit, $this->transactions))(
        new RegisterStudentCommand(new StudentFields('Ana', 'Pérez', 'female', '2019-03-04', 'V', 'school_id', 'V11915432100', null, null, null, null, null, [
            new GuardianFields(null, 'María', 'Mora', 'national_id', 'V15432100', '0414', null, null, 'mother', true, true),
        ]), 'Penicilina', null, null),
    );
    $this->audit->recorded = [];
});

/**
 * A pending request of the destination (9) for the origin's student.
 */
function pendingRequest(string $requestedAt = '2026-10-05 08:00'): TransferRequestId
{
    return test()->transfers->save(TransferRequest::request(
        InstitutionId::fromInt(9),
        StudentIdentification::fromInput('school_id', 'V11915432100'),
        'Colegio Sur',
        new DateTimeImmutable($requestedAt),
    ));
}

function actAsDestination(): void
{
    test()->tenant->set(new TenantContext(InstitutionId::fromInt(9), 'Colegio Sur', 'sur'));
}

function requestTransfer(string $identification = 'V30123456', string $type = 'national_id'): TransferRequestId
{
    $test = test();

    return (new RequestTransferHandler($test->transfers, $test->students, $test->tenant, $test->permissions, $test->clock, $test->audit, $test->transactions))($type, $identification);
}

function approveTransfer(TransferRequestId $id): void
{
    $test = test();
    (new ApproveTransferHandler($test->transfers, $test->students, $test->guardians, $test->copier, $test->tenant, $test->permissions, $test->clock, $test->audit, $test->transactions))($id->value);
}

function rejectTransfer(TransferRequestId $id, string $reason = 'Falta la solvencia'): void
{
    $test = test();
    (new RejectTransferHandler($test->transfers, $test->students, $test->tenant, $test->permissions, $test->clock, $test->audit, $test->transactions))($id->value, $reason);
}

it('asks for a student by its identification, recorded in the destination', function () {
    actAsDestination();

    $id = requestTransfer();

    $request = $this->transfers->byId($id);
    expect($request->destinationId()->value)->toBe(9)
        ->and($request->destinationName())->toBe('Colegio Sur')
        ->and($request->identification()->value())->toBe('V30123456')
        ->and($this->audit->types())->toBe(['students.transfer.requested'])
        ->and($this->audit->recorded[0]['inTransaction'])->toBeTrue();
});

it('answers the same whether or not some institution has the student', function () {
    actAsDestination();
    // The destination's own students: in memory there is no Row Level Security to hide the origin's.
    $this->students = new InMemoryStudentRepository;

    $unknown = requestTransfer('V99999999');
    $known = requestTransfer('V11915432100', 'school_id');

    expect($this->transfers->byId($unknown)->status($this->clock->now()))->toBe(TransferStatus::Pending)
        ->and($this->transfers->byId($known)->status($this->clock->now()))->toBe(TransferStatus::Pending);
});

it('refuses to ask for a student the institution already has', function () {
    try {
        requestTransfer('V11915432100', 'school_id');
    } catch (BusinessRuleViolations $violations) {
        expect($violations->messagesByField())->toBe(['identification' => ['Ese estudiante ya está registrado en tu plantel.']]);

        return;
    }

    $this->fail('The request was accepted.');
});

it('refuses a second pending request for the same student, and allows one after the first expired', function () {
    actAsDestination();
    requestTransfer();

    expect(fn () => requestTransfer())->toThrow(BusinessRuleViolations::class);

    $this->clock->now = new DateTimeImmutable('2026-10-26 09:00');
    requestTransfer();

    expect(array_map(fn (TransferRequest $request) => $request->status($this->clock->now())->value, array_values($this->transfers->requests)))
        ->toBe(['expired', 'pending']);
});

it('approves: withdraws the student here and copies its record to the destination', function () {
    $id = pendingRequest();

    approveTransfer($id);

    $student = $this->students->byId($this->studentId);
    $copy = $this->copier->copies[0];
    expect($this->transfers->byId($id)->status($this->clock->now()))->toBe(TransferStatus::Approved)
        ->and($this->transfers->byId($id)->originName())->toBe('Colegio Norte')
        ->and($student->status())->toBe(StudentStatus::Withdrawn)
        ->and($student->withdrawals()[0]->reason)->toBe(WithdrawalReason::ChangedSchool)
        ->and($student->withdrawals()[0]->withdrawnOn->format('Y-m-d'))->toBe('2026-10-10')
        ->and($copy['destination']->value)->toBe(9)
        ->and($copy['data']->identification->value())->toBe('V11915432100')
        ->and($copy['data']->health->allergies)->toBe('Penicilina')
        ->and($copy['data']->guardians[0]->data->document->value())->toBe('V15432100')
        ->and($copy['data']->originName)->toBe('Colegio Norte')
        ->and($this->audit->types())->toBe(['students.student.withdrawn', 'students.transfer.approved']);
});

it('tells the origin to reject when the destination already registered the student', function () {
    $id = pendingRequest();
    $this->copier->destinationHasTheStudent = true;

    expect(fn () => approveTransfer($id))->toThrow(Conflict::class, 'El plantel destino ya registró a ese estudiante; rechaza la solicitud.');
});

it('rejects with a reason, recorded, the student staying', function () {
    $id = pendingRequest();

    rejectTransfer($id);

    expect($this->transfers->byId($id)->status($this->clock->now()))->toBe(TransferStatus::Rejected)
        ->and($this->transfers->byId($id)->rejectionReason())->toBe('Falta la solvencia')
        ->and($this->students->byId($this->studentId)->status())->toBe(StudentStatus::Active)
        ->and($this->audit->types())->toBe(['students.transfer.rejected']);
});

it('does not answer an expired request', function () {
    $id = pendingRequest('2026-09-20 08:00');

    expect(fn () => approveTransfer($id))->toThrow(BusinessRuleViolation::class, 'La solicitud caducó.')
        ->and($this->copier->copies)->toBe([]);
});

it('treats a request it can not answer as not found', function () {
    $id = pendingRequest();
    $this->students->students = [];

    expect(fn () => approveTransfer($id))->toThrow(TransferNotFound::class)
        ->and(fn () => approveTransfer(TransferRequestId::fromInt(99)))->toThrow(TransferNotFound::class);
});

it('refuses whoever may not manage students', function () {
    $this->permissions->manage = false;

    expect(fn () => requestTransfer())->toThrow(AuthorizationDenied::class)
        ->and(fn () => approveTransfer(pendingRequest()))->toThrow(AuthorizationDenied::class);
});
