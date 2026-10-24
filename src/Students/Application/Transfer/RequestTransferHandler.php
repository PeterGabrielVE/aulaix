<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Transfer;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\Clock;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Domain\Identity\StudentIdentification;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Transfer\TransferRequest;
use AulaX\Students\Domain\Transfer\TransferRequestId;
use AulaX\Students\Domain\Transfer\TransferRequestRepository;
use AulaX\Tenancy\Application\CurrentTenant;
use LogicException;

/**
 * The destination asks for a student by its identification (EST-R07.1). The
 * answer never depends on whether another institution has it (EST-R07.2):
 * the request simply waits for an origin, or expires.
 */
final readonly class RequestTransferHandler
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
     * @throws BusinessRuleViolations
     */
    public function __invoke(string $identificationType, string $identification): TransferRequestId
    {
        StudentAccess::ensureCanManage($this->permissions);
        $destination = $this->tenant->context() ?? throw new LogicException('A transfer is requested from an open institution.');

        try {
            $wanted = StudentIdentification::fromInput($identificationType, $identification);
        } catch (BusinessRuleViolation $invalid) {
            throw BusinessRuleViolations::of($invalid);
        }

        return $this->transactions->run(function () use ($destination, $wanted) {
            $now = $this->clock->now();

            if ($this->students->isIdentificationTaken($wanted, null)) {
                throw BusinessRuleViolations::of(BusinessRuleViolation::onField('identification', 'Ese estudiante ya está registrado en tu plantel.'));
            }

            $previous = $this->transfers->pendingOf($destination->institutionId, $wanted);

            if ($previous !== null) {
                // A pending request blocks a new one; an expired one is closed for good.
                try {
                    $previous->expire($now);
                } catch (BusinessRuleViolation) {
                    throw BusinessRuleViolations::of(BusinessRuleViolation::onField('identification', 'Ya pediste el traslado de ese estudiante; espera la respuesta.'));
                }

                $this->transfers->save($previous);
            }

            $request = TransferRequest::request($destination->institutionId, $wanted, $destination->name, $now);
            $request->identify($this->transfers->save($request));
            $this->audit->record(TransferAudit::requested($request));

            return $request->id();
        });
    }
}
