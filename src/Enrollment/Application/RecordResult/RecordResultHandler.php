<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\RecordResult;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\EnrollmentAudit;
use AulaX\Enrollment\Application\EnrollmentNotFound;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Enrollment\Domain\Outcome;
use AulaX\Enrollment\Domain\PromotionResult;
use AulaX\Enrollment\Domain\ResultNotAllowed;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * A manager records the result of a student's year (MAT-R06), once the
 * year's third term is closed (MAT-R06.1, R06.2), and corrects it while the
 * student is not enrolled in the next year yet (MAT-R06.8, R06.9). Under
 * the calendar lock and recorded, with the result it replaced.
 */
final readonly class RecordResultHandler
{
    public function __construct(
        private EnrollmentRepository $enrollments,
        private StructureFacts $structure,
        private CalendarFacts $calendar,
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
    public function __invoke(RecordResultCommand $command): void
    {
        EnrollmentAccess::ensureCanManage($this->permissions);

        $this->transactions->run(function () use ($command) {
            $this->calendar->lock();
            $enrollment = $command->enrollmentId > 0 ? $this->enrollments->byId(EnrollmentId::fromInt($command->enrollmentId)) : null;

            if ($enrollment === null) {
                throw EnrollmentNotFound::create();
            }

            $year = $enrollment->schoolYearId();

            if ($this->calendar->statusOf($year) !== 'active' || ! $this->calendar->lastTermClosed($year)) {
                throw ResultNotAllowed::outcome('El resultado se registra con el año activo y su tercer lapso cerrado.');
            }

            $next = $this->calendar->nextOf($year);

            if ($next !== null && $this->enrollments->currentOf($enrollment->studentId(), $next) !== null) {
                throw ResultNotAllowed::outcome('El estudiante ya está inscrito en el año siguiente: su resultado no cambia.');
            }

            $outcome = Outcome::tryFrom((string) $command->outcome) ?? throw ResultNotAllowed::outcome('Indica el resultado.');
            $before = $enrollment->result();
            $enrollment->recordResult(
                PromotionResult::of($outcome, $command->pendingSubjects),
                $this->structure->curriculum($year, $enrollment->placement()->grade),
                $this->clock->now(),
                $command->actorId,
            );
            $this->enrollments->save($enrollment);
            $this->audit->record(EnrollmentAudit::resultRecorded($before, $enrollment));
        });
    }
}
