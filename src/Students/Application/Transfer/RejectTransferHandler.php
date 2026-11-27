<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Transfer\TransferRequestId;
use AulaX\Students\Domain\Transfer\TransferRequestRepository;
use AulaX\Tenancy\Application\CurrentTenant;
use LogicException;

/**
 * The origin rejects a request for one of its active students with a reason
 * the destination will see (EST-R07.6). The student stays.
 */
final readonly class RejectTransferHandler
{
    public function __construct(
        private TransferRequestRepository $transfers,
        private StudentRepository $students,
        private CurrentTenant $tenant,
        private StudentPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws TransferNotFound
     * @throws BusinessRuleViolation When it was answered, expired or has no reason.
     */
    public function __invoke(int $transferId, string $reason): void
    {
        StudentAccess::ensureCanManage($this->permissions);
        $origin = $this->tenant->context() ?? throw new LogicException('A transfer is rejected from an open institution.');

        $this->transactions->run(function () use ($transferId, $reason, $origin) {
            $request = $transferId > 0 ? $this->transfers->byId(TransferRequestId::fromInt($transferId)) : null;

            if ($request === null || $this->students->activeByIdentification($request->identification()) === null) {
                throw TransferNotFound::create();
            }

            $request->reject($origin->institutionId, $origin->name, $reason, $this->clock->now());
            $this->transfers->save($request);
            $this->audit->record(TransferAudit::rejected($request));
        });
    }
}
