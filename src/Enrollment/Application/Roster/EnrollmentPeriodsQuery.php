<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\EnrollmentRepository;

/**
 * The public API for SPEC-011: when each student was in each section of a
 * school year, oldest first, so an activity applies only to who was there
 * on its date. An annulled enrollment never was. Who may see them is the
 * caller's check.
 */
final readonly class EnrollmentPeriodsQuery
{
    public function __construct(
        private EnrollmentList $enrollments,
        private EnrollmentRepository $repository,
    ) {}

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

    /**
     * Which of these students already have the result of the year recorded
     * (SPEC-016 RVP-R01.7).
     *
     * @param  list<int>  $studentIds
     * @return list<int>
     */
    public function withResult(int $schoolYearId, array $studentIds): array
    {
        $students = [];

        foreach ($this->enrollments->ofStudentsInYear($schoolYearId, $studentIds) as $enrollment) {
            if ($enrollment->result() !== null) {
                $students[] = $enrollment->studentId();
            }
        }

        return array_values(array_unique($students));
    }

    /**
     * Whose enrollment it is, in which year and when (SPEC-011 EVA-R09.1:
     * whether it has grades). Null for one the institution does not have.
     */
    public function ofEnrollment(int $enrollmentId): ?EnrolledPeriod
    {
        $enrollment = $enrollmentId > 0 ? $this->repository->byId(EnrollmentId::fromInt($enrollmentId)) : null;

        return $enrollment === null ? null : new EnrolledPeriod($enrollment->studentId(), $enrollment->schoolYearId(), self::view($enrollment));
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
