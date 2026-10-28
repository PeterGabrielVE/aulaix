<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\AnnulEnrollment;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\EnrollmentAudit;
use AulaX\Enrollment\Application\EnrollmentNotFound;
use AulaX\Enrollment\Application\GradedEnrollments;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Domain\AnnulmentReason;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * A manager annuls an enrollment made by mistake, with a reason; after 48
 * hours or with grades, only a directive (MAT-R14.1 – R14.4). Kept, never
 * deleted, and recorded (MAT-R12.1).
 */
final readonly class AnnulEnrollmentHandler
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private CalendarFacts $calendar,
        private GradedEnrollments $graded,
        private EnrollmentPermissions $permissions,
        private Clock $clock,
        private AuditTrail $audit,
        private TransactionManager $transactions,
    ) {}

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws EnrollmentNotFound
     */
    public function __invoke(AnnulEnrollmentCommand $command): void
    {
        EnrollmentAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($command) {
            $this->calendar->lock();
            $enrollment = $command->enrollmentId > 0 ? $this->enrollments->byId(EnrollmentId::fromInt($command->enrollmentId)) : null;

            if ($enrollment === null) {
                throw EnrollmentNotFound::create();
            }

            $enrollment->annul(
                AnnulmentReason::tryFrom((string) $command->reason),
                $command->note,
                $command->actorId,
                $this->clock->now(),
                $this->permissions->canApproveExceptions(),
                $this->graded->hasGrades($command->enrollmentId),
            );
            $this->enrollments->save($enrollment);
            $this->audit->record(EnrollmentAudit::annulled($enrollment));
        });
    }
}
