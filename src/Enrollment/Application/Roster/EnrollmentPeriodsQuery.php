<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\Enrollment;

/**
 * The public API for SPEC-011: when each student was in each section of a
 * school year, oldest first, so an activity applies only to who was there
 * on its date. An annulled enrollment never was. Who may see them is the
 * caller's check.
 */
final readonly class EnrollmentPeriodsQuery
{
    public function __construct(private EnrollmentList $enrollments) {}

    /**
     * @param  list<int>  $studentIds
     * @return array<int, list<PeriodView>> Student id => periods; students with none are left out.
     */
    public function __invoke(int $schoolYearId, array $studentIds): array
    {
        $periods = [];

        foreach ($this->enrollments->ofStudentsInYear($schoolYearId, $studentIds) as $enrollment) {
            if ($enrollment->period()->endReason === EndReason::Annulled) {
                continue;
            }

            $periods[$enrollment->studentId()][] = self::view($enrollment);
        }

        return $periods;
    }

    private static function view(Enrollment $enrollment): PeriodView
    {
        return new PeriodView(
            $enrollment->placement()->sectionId,
            $enrollment->placement()->grade,
            $enrollment->period()->startsOn->format('Y-m-d'),
            $enrollment->period()->endsOn?->format('Y-m-d'),
        );
    }
}
