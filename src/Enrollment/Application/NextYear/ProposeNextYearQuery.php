<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\NextYear;

use AulaX\Enrollment\Application\EnrollmentAccess;
use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Application\Port\CalendarFacts;
use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Enrollment\Application\Port\StructureFacts;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * What enrolling a year's students in the next one would do (MAT-R07.1 –
 * R07.3, R07.6, R07.7, R15.3): the grade of each, and who is left out and
 * why. Nothing is written.
 */
final readonly class ProposeNextYearQuery
{
    private NextYearPlanner $planner;

    public function __construct(
        EnrollmentList $list,
        StudentFacts $students,
        StructureFacts $structure,
        CalendarFacts $calendar,
        AgeRules $ageRules,
        private EnrollmentPermissions $permissions,
    ) {
        $this->planner = new NextYearPlanner($list, $students, $structure, $calendar, $ageRules, $permissions);
    }

    /**
     * @throws AuthorizationDenied
     * @throws BusinessRuleViolation
     */
    public function __invoke(int $fromSchoolYearId): NextYearProposal
    {
        EnrollmentAccess::ensureCanManage($this->permissions);

        return $this->planner->plan($fromSchoolYearId);
    }
}
