<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

use AulaX\Enrollment\Domain\Enrollment;

/**
 * Read side of the enrollments of the open institution (A-08).
 */
interface EnrollmentList
{
    /**
     * @return array<int, int> Section id => current enrollments, for the sections that have any.
     */
    public function currentCountsOf(int $schoolYearId): array;

    /**
     * @return list<Enrollment> The current enrollments of a section.
     */
    public function currentIn(int $sectionId): array;

    /**
     * @return list<int> Students with a current enrollment in the year.
     */
    public function currentStudentsOf(int $schoolYearId): array;

    /**
     * @return list<Enrollment> The current enrollments of the year.
     */
    public function currentInYear(int $schoolYearId): array;

    /**
     * @return list<Enrollment> The current enrollments of the year without a result (MAT-R16).
     */
    public function currentWithoutResult(int $schoolYearId): array;

    /**
     * Whether the year has any enrollment, current, ended or annulled.
     */
    public function hasAnyInYear(int $schoolYearId): bool;

    /**
     * @return list<Enrollment> Every enrollment of a student, oldest first.
     */
    public function historyOf(int $studentId): array;
}
