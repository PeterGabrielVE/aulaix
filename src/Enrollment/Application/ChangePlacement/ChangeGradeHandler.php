<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\ChangePlacement;

use AulaX\Audit\Application\AuditTrail;
use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\EnrollmentNotFound;
use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Domain\EnrollmentRepository;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\Clock;

/**
 * A directive moves a student to a section of another grade of the same
 * year, exceptionally (MAT-R04.7 – R04.9): recorded with who approved it.
 */
final readonly class ChangeGradeHandler
{
    private PlacementChange $change;

    public function __construct(
        EnrollmentRepository $enrollments,
        StudentFacts $students,
        StructureFacts $structure,
        CalendarFacts $calendar,
        AgeRules $ageRules,
        private EnrollmentPermissions $permissions,
        Clock $clock,
        AuditTrail $audit,
        TransactionManager $transactions,
    ) {
        $this->change = new PlacementChange($enrollments, $students, $structure, $calendar, $ageRules, $permissions, $clock, $audit, $transactions);
    }

    /**
     * @return int The id of the new enrollment.
     *
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     * @throws EnrollmentNotFound
     */
    public function __invoke(ChangePlacementCommand $command): int
    {
        EnrollmentAccess::ensureCanManage($this->permissions);
        EnrollmentAccess::ensureCanApproveExceptions($this->permissions);

        return ($this->change)($command, toAnotherGrade: true);
    }
}
