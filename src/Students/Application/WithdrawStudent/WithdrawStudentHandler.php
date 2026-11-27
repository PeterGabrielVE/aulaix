<?php

declare(strict_types=1);

namespace AulaX\Students\Application\WithdrawStudent;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\BusinessRuleViolations;
use AulaX\Shared\Domain\Clock;
use AulaX\Students\Application\Port\StudentPermissions;
use AulaX\Students\Application\StudentAccess;
use AulaX\Students\Application\StudentAudit;
use AulaX\Students\Application\StudentNotFound;
use AulaX\Students\Application\WithdrawalHooks;
use AulaX\Students\Domain\Student\StudentId;
use AulaX\Students\Domain\Student\StudentRepository;
use AulaX\Students\Domain\Student\Withdrawal;
use DateTimeImmutable;

/**
 * A manager withdraws a student with a date and a reason, its record kept
 * (EST-R06.1 – R06.3), recorded in the audit log (EST-R10.2).
 */
final readonly class WithdrawStudentHandler
{
    public function __construct(
        private StudentRepository $students,
        private StudentPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
        private WithdrawalHooks $hooks = new WithdrawalHooks([]),
    ) {}

    /**
     * @throws BusinessRuleViolations
     * @throws StudentNotFound
     */
    public function __invoke(WithdrawStudentCommand $command): void
    {
        StudentAccess::ensureCanManage($this->permissions);
        $today = new DateTimeImmutable($this->clock->now()->format('Y-m-d'));
        $withdrawal = Withdrawal::fromInput($command->withdrawnOn, $command->reason, $command->note, $today);

        $this->transactions->run(function () use ($command, $withdrawal) {
            $id = $command->studentId > 0 ? StudentId::fromInt($command->studentId) : throw StudentNotFound::create();
            $student = $this->students->byId($id) ?? throw StudentNotFound::create();

            $student->withdraw($withdrawal);
            $this->students->save($student);
            $this->audit->record(StudentAudit::withdrawn($student, $withdrawal));
            $this->hooks->studentWithdrawn($id->value, $withdrawal->withdrawnOn, false);
        });
    }
}
