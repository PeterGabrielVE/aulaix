<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Domain\InstitutionId;
use AulaX\Students\Application\Port\TransferCopier;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Application\Transfer\StudentTransferData;
use AulaX\Students\Application\Transfer\TransferredGuardian;
use AulaX\Students\Domain\Guardian\Guardian;
use AulaX\Students\Domain\Guardian\GuardianRepository;
use AulaX\Students\Domain\Student\Guardianship;
use AulaX\Students\Domain\Student\Student;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Tenancy\Application\CurrentTenant;
use AulaX\Tenancy\Application\InstitutionContexts;
use AulaX\Tenancy\Application\TenantActivator;
use LogicException;

/**
 * ADR 0008: writes the record in the destination, within() its savepoint
 * (ADR 0011), which activates it (Row Level Security and permission team)
 * and gives the origin back whatever happens. A failure in the destination
 * rolls the savepoint back; the origin's transaction then rolls back
 * everything.
 */
final readonly class TenantSwitchingTransferCopier implements TransferCopier
{
    public function __construct(
        private TenantActivator $activator,
        private CurrentTenant $tenant,
        private InstitutionContexts $institutions,
        private StudentRepository $students,
        private GuardianRepository $guardians,
        private AuditTrail $audit,
    ) {}

    public function copyTo(InstitutionId $destination, StudentTransferData $data): void
    {
        $this->tenant->context() ?? throw new LogicException('A transfer is copied from an open institution.');
        $target = $this->institutions->of($destination) ?? throw new LogicException("The destination institution {$destination->value} does not exist.");

        $this->activator->within($target, fn () => $this->write($data));
    }

    private function write(StudentTransferData $data): void
    {
        $guardianships = array_map(fn (TransferredGuardian $guardian) => $this->link($guardian), $data->guardians);

        $student = Student::register($data->personalData, $data->identification, $guardianships, $data->health);
        $student->identify($this->students->save($student));

        $this->audit->record(StudentAudit::transferredIn($student, $data->originName));
    }

    /**
     * The destination's own guardian with that document, or a new one,
     * never with the origin's account link (EST-R07.8).
     */
    private function link(TransferredGuardian $transferred): Guardianship
    {
        $guardian = $this->guardians->byDocument($transferred->data->document);

        if ($guardian === null) {
            $guardian = Guardian::register($transferred->data);
            $guardian->identify($this->guardians->save($guardian));
        }

        return new Guardianship($guardian->id(), $guardian->data()->document, $transferred->relationship, $transferred->isLegal, $transferred->isPrimary);
    }
}
