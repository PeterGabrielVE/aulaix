<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ReinstateStudent;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Domain\Student\StudentId;
use AulaX\Students\Domain\Student\StudentRepository;
use DateTimeImmutable;

/**
 * A manager reinstates a withdrawn student, the history of its withdrawals
 * kept (EST-R06.4), recorded in the audit log (EST-R10.2).
 */
final readonly class ReinstateStudentHandler
{
    public function __construct(
        private StudentRepository $students,
        private StudentPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws BusinessRuleViolation When the student is active.
     * @throws StudentNotFound
     */
    public function __invoke(int $studentId): void
    {
        StudentAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($studentId) {
            $id = $studentId > 0 ? StudentId::fromInt($studentId) : throw StudentNotFound::create();
            $student = $this->students->byId($id) ?? throw StudentNotFound::create();

            $student->reinstate(new DateTimeImmutable($this->clock->now()->format('Y-m-d')));
            $this->students->save($student);
            $this->audit->record(StudentAudit::reinstated($student));
        });
    }
}
