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
 * A manager moves a student to another section of its grade during the year
 * (MAT-R04.1 – R04.5).
 */
final readonly class ChangeSectionHandler
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

        return ($this->change)($command, toAnotherGrade: false);
    }
}
