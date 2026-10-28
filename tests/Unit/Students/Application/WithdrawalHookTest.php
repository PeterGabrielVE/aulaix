<?php

use AulaX\Catalogs\Application\Countries;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Application\Port\WithdrawalHook;
use AulaX\Students\Application\RegisterStudent\RegisterStudentCommand;
use AulaX\Students\Application\RegisterStudent\RegisterStudentHandler;
use AulaX\Students\Application\StudentForm\GuardianFields;
use AulaX\Students\Application\StudentForm\StudentFields;
use AulaX\Students\Application\StudentForm\StudentForm;
use AulaX\Students\Application\Transfer\ApproveTransferHandler;
use AulaX\Students\Application\WithdrawalHooks;
use AulaX\Students\Application\WithdrawStudent\WithdrawStudentCommand;
use AulaX\Students\Application\WithdrawStudent\WithdrawStudentHandler;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Transfer\TransferRequest;
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
 * SPEC-010 MAT-R05.1, R05.2, R05.5 (ADR 0012): withdrawing or transferring a
 * student tells the registered hooks, inside the same transaction.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->students = new InMemoryStudentRepository;
    $this->guardians = new InMemoryGuardianRepository;
    $this->permissions = new SwitchableStudentPermissions;
    $this->clock = new FixedClock(new DateTimeImmutable('2026-10-21 09:00'));
    $form = new StudentForm($this->guardians, $this->students, new Countries, new StubGeographyDirectory, $this->clock);
    $this->studentId = (new RegisterStudentHandler($form, $this->students, $this->permissions, $this->audit, $this->transactions))(
        new RegisterStudentCommand(new StudentFields('Ana', 'Pérez', 'female', '2019-03-04', 'V', 'school_id', 'V11915432100', null, null, null, null, null, [
            new GuardianFields(null, 'María', 'Mora', 'national_id', 'V15432100', '0414', null, null, 'mother', true, true),
        ]), null, null, null),
    );
    $this->hook = new class($this->transactions) implements WithdrawalHook
    {
        /** @var list<array{int, string, bool, bool}> */
        public array $calls = [];

        public bool $fails = false;

        public function __construct(private readonly InlineTransactionManager $transactions) {}

        public function studentWithdrawn(int $studentId, DateTimeImmutable $on, bool $transferred): void
        {
            $this->calls[] = [$studentId, $on->format('Y-m-d'), $transferred, $this->transactions->inTransaction];

            if ($this->fails) {
                throw new RuntimeException('The enrollment could not end.');
            }
        }
    };
});

function withdrawWithHook(): void
{
    $test = test();
    (new WithdrawStudentHandler($test->students, $test->permissions, $test->clock, $test->audit, $test->transactions, new WithdrawalHooks([$test->hook])))(
        new WithdrawStudentCommand($test->studentId->value, '2026-10-20', 'moved', null),
    );
}

it('a withdrawal tells the hooks its date, inside the transaction', function () {
    withdrawWithHook();

    expect($this->hook->calls)->toBe([[$this->studentId->value, '2026-10-20', false, true]]);
});

it('if a hook fails, the withdrawal fails with it', function () {
    $this->hook->fails = true;

    expect(fn () => withdrawWithHook())->toThrow(RuntimeException::class, 'The enrollment could not end.')
        ->and($this->hook->calls[0][3])->toBeTrue();
});

it('a transfer tells the hooks as a transfer, inside the transaction', function () {
    $tenant = new InMemoryCurrentTenant;
    $tenant->set(new TenantContext(InstitutionId::fromInt(4), 'Colegio Norte', 'norte'));
    $transfers = new InMemoryTransferRequestRepository;
    $request = $transfers->save(TransferRequest::request(
        InstitutionId::fromInt(9),
        StudentIdentification::fromInput('school_id', 'V11915432100'),
        'Colegio Sur',
        new DateTimeImmutable('2026-10-15 08:00'),
    ));

    (new ApproveTransferHandler($transfers, $this->students, $this->guardians, new RecordingTransferCopier, $tenant, $this->permissions, $this->clock, $this->audit, $this->transactions, new WithdrawalHooks([$this->hook])))($request->value);

    expect($this->hook->calls)->toBe([[$this->studentId->value, '2026-10-21', true, true]]);
});

it('with no hook registered, nothing else happens', function () {
    expect(fn () => (new WithdrawalHooks([]))->studentWithdrawn(1, new DateTimeImmutable, false))->not->toThrow(Throwable::class);
});
