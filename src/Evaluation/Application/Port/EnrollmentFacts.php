<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Port;

use AulaX\Evaluation\Domain\EnrollmentPeriod;
use AulaX\Evaluation\Domain\OwedPending;

/**
 * The enrollments of the open institution, as Enrollment tells them (A-06).
 */
interface EnrollmentFacts
{
    /**
     * @return list<RosterStudentFact> The students currently enrolled in the section, by name.
     */
    public function roster(int $schoolYearId, int $sectionId): array;

    /**
     * @param  list<int>  $studentIds
     * @return array<int, list<EnrollmentPeriod>> Student id => its periods of the year.
     */
    public function periods(int $schoolYearId, array $studentIds): array;

    /**
     * Whose enrollment it is, in which year and when; null for one the
     * institution does not have.
     *
     * @return ?array{studentId: int, schoolYearId: int, period: EnrollmentPeriod}
     */
    public function enrollment(int $enrollmentId): ?array;

    /**
     * Which of these students already have the result of the year recorded
     * (SPEC-010): their revisions no longer change (RVP-R01.7).
     *
     * @param  list<int>  $studentIds
     * @return list<int>
     */
    public function resultsRecorded(int $schoolYearId, array $studentIds): array;

    /**
     * The pending subjects from the results of the years before, of the
     * students enrolled in the year or waiting to graduate (RVP-R03.1 –
     * R03.3); approved ones included, it is Evaluation that knows them.
     *
     * @return list<OwedPending>
     */
    public function owedPendings(int $schoolYearId): array;

    /**
     * @param  list<int>  $studentIds
     * @return array<int, RosterStudentFact> Student id => name and identification; the ones it does not have are left out.
     */
    public function students(array $studentIds): array;
}
