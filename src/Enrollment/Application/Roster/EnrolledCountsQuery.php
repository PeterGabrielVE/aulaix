<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Enrollment\Application\Port\EnrollmentList;

/**
 * The public API for SPEC-008 and SPEC-013 (MAT-R09.3): the current
 * enrollments of each section of a school year of the open institution.
 * Who may see them is the caller's check.
 */
final readonly class EnrolledCountsQuery
{
    public function __construct(private EnrollmentList $enrollments) {}

    /**
     * @return array<int, int> Section id => current enrollments, for the sections that have any.
     */
    public function __invoke(int $schoolYearId): array
    {
        return $this->enrollments->currentCountsOf($schoolYearId);
    }
}
