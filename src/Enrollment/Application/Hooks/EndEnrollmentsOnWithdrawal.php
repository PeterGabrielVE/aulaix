<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Hooks;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Enrollment\Application\EnrollmentAudit;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Students\Application\Port\WithdrawalHook;
use DateTimeImmutable;

/**
 * Students' extension point (ADR 0012): a withdrawn or transferred student
 * ends its current enrollments, inside the withdrawal's transaction
 * (MAT-R05.1, R05.2, R05.5). Reinstated, it has none until enrolled again
 * (MAT-R05.3).
 */
final readonly class EndEnrollmentsOnWithdrawal implements WithdrawalHook
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private CalendarFacts $calendar,
        private AuditTrail $audit,
    ) {}

    public function studentWithdrawn(int $studentId, DateTimeImmutable $on, bool $transferred): void
    {
        $current = $this->enrollments->currentOfStudent($studentId);

        if ($current === []) {
            return;
        }

        $this->calendar->lock();

        foreach ($current as $enrollment) {
            $enrollment->end($on, $transferred ? EndReason::Transfer : EndReason::Withdrawal);
            $this->enrollments->save($enrollment);
            $this->audit->record(EnrollmentAudit::ended($enrollment));
        }
    }
}
