<?php

declare(strict_types=1);

namespace AulaX\Students\Application\UpdateGuardian;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Application\GuardianNotFound;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Domain\Guardian\GuardianData;
use AulaX\Students\Domain\Guardian\GuardianId;
use AulaX\Students\Domain\Guardian\GuardianRepository;

/**
 * A manager corrects a guardian's data, once for every student it
 * represents (EST-R03.8), recorded with old and new values (EST-R10.1).
 */
final readonly class UpdateGuardianHandler
{
    public function __construct(
        private GuardianRepository $guardians,
        private StudentPermissions $permissions,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws BusinessRuleViolations
     * @throws GuardianNotFound
     */
    public function __invoke(UpdateGuardianCommand $command): void
    {
        StudentAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($command) {
            $id = $command->guardianId > 0 ? GuardianId::fromInt($command->guardianId) : throw GuardianNotFound::create();
            $guardian = $this->guardians->byId($id) ?? throw GuardianNotFound::create();
            $data = GuardianData::fromInput(
                $command->givenNames,
                $command->familyNames,
                $command->documentType,
                $command->document,
                $command->phone,
                $command->email,
                $command->address,
            );

            $other = $this->guardians->byDocument($data->document);

            if ($other !== null && ! $other->id()->equals($id)) {
                throw BusinessRuleViolations::of(BusinessRuleViolation::onField('document', 'Ya hay un representante con ese documento en el plantel.'));
            }

            $before = StudentAudit::guardianDetails($guardian);
            $guardian->correct($data);
            $event = StudentAudit::guardianUpdated($before, $guardian);

            if ($event->changes === []) {
                return;
            }

            $this->guardians->save($guardian);
            $this->audit->record($event);
        });
    }
}
