<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ChangeHealth;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Domain\Student\HealthRecord;
use AulaX\Students\Domain\Student\StudentId;
use AulaX\Students\Domain\Student\StudentRepository;

/**
 * A manager changes a student's health data (EST-R04.1). The audit log keeps
 * which data changed, never their values (EST-R10.3, NF02).
 */
final readonly class ChangeHealthHandler
{
    public function __construct(
        private StudentRepository $students,
        private StudentPermissions $permissions,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws BusinessRuleViolations
     * @throws StudentNotFound
     */
    public function __invoke(ChangeHealthCommand $command): void
    {
        StudentAccess::ensureCanManage($this->permissions);
        $health = HealthRecord::fromInput($command->allergies, $command->medicalConditions, $command->disability);

        $this->transactions->run(function () use ($command, $health) {
            $id = $command->studentId > 0 ? StudentId::fromInt($command->studentId) : throw StudentNotFound::create();
            $student = $this->students->byId($id) ?? throw StudentNotFound::create();
            $before = $student->health();

            if ($before->fields() === $health->fields()) {
                return;
            }

            $student->changeHealth($health);
            $this->students->save($student);
            $this->audit->record(StudentAudit::healthChanged($before, $health, $student));
        });
    }
}
