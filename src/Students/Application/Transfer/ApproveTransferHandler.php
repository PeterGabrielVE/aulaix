<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Shared\Domain\Conflict;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\Port\TransferCopier;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Student\Withdrawal;
use AulaX\Students\Domain\Student\WithdrawalReason;
use AulaX\Students\Domain\Transfer\TransferRequestId;
use AulaX\Students\Domain\Transfer\TransferRequestRepository;
use AulaX\Tenancy\Application\CurrentTenant;
use DateTimeImmutable;
use LogicException;

/**
 * The origin approves a request for one of its active students (EST-R07.5),
 * in one transaction (ADR 0008): the request is answered, the student is
 * withdrawn here for a change of school, and its record is created in the
 * destination. Either all of it happens, or none.
 */
final readonly class ApproveTransferHandler
{
    public function __construct(
        private TransferRequestRepository $transfers,
        private StudentRepository $students,
        private GuardianRepository $guardians,
        private TransferCopier $copier,
        private CurrentTenant $tenant,
        private StudentPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws TransferNotFound
     * @throws BusinessRuleViolation When it was answered or expired.
     * @throws Conflict When the destination already has the student.
     */
    public function __invoke(int $transferId): void
    {
        StudentAccess::ensureCanManage($this->permissions);
        $origin = $this->tenant->context() ?? throw new LogicException('A transfer is approved from an open institution.');

        $this->transactions->run(function () use ($transferId, $origin) {
            $now = $this->clock->now();
            $request = $transferId > 0 ? $this->transfers->byId(TransferRequestId::fromInt($transferId)) : null;
            $student = $request === null ? null : $this->students->activeByIdentification($request->identification());

            if ($request === null || $student === null) {
                throw TransferNotFound::create();
            }

            $request->approve($origin->institutionId, $origin->name, $now);
            $this->transfers->save($request);

            $withdrawal = new Withdrawal(new DateTimeImmutable($now->format('Y-m-d')), WithdrawalReason::ChangedSchool, "Traslado a {$request->destinationName()}");
            $student->withdraw($withdrawal);
            $this->students->save($student);
            $this->audit->record(StudentAudit::withdrawn($student, $withdrawal));

            try {
                $this->copier->copyTo($request->destinationId(), $this->dataOf($student, $origin->name));
            } catch (Conflict $alreadyThere) {
                throw new Conflict('El plantel destino ya registró a ese estudiante; rechaza la solicitud.', previous: $alreadyThere);
            }

            $this->audit->record(TransferAudit::approved($request));
        });
    }

    private function dataOf(Student $student, string $originName): StudentTransferData
    {
        return new StudentTransferData(
            $student->personalData(),
            $student->identification(),
            $student->health(),
            array_map(function (Guardianship $link) {
                $guardian = $this->guardians->byId($link->guardianId) ?? throw new LogicException("Guardian {$link->guardianId->value} is missing.");

                return new TransferredGuardian($guardian->data(), $link->relationship, $link->isLegal, $link->isPrimary);
            }, $student->guardianships()),
            $originName,
        );
    }
}
