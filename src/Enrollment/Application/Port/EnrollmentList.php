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
     * @return array<string, int> Grade => current enrollments, for the grades that have any.
     */
    public function currentCountsByGrade(int $schoolYearId): array;

    /**
     * @return list<Enrollment> The current enrollments of a section.
     */
    public function currentIn(int $sectionId): array;

    /**
     * @return list<int> Students with a current enrollment in the year.
     */
    public function currentStudentsOf(int $schoolYearId): array;

    /**
     * Students with a current enrollment of the year in one of the sections
     * or grades (MAT-R08.1).
     *
     * @param  list<int>  $sectionIds
     * @param  list<string>  $grades
     * @return list<int>
     */
    public function currentStudentsIn(int $schoolYearId, array $sectionIds, array $grades): array;

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
     * @param  list<int>  $studentIds
     * @return list<Enrollment> Every enrollment of these students in the year, oldest first.
     */
    public function ofStudentsInYear(int $schoolYearId, array $studentIds): array;

    /**
     * @return list<Enrollment> Every enrollment of a student, oldest first.
     */
    public function historyOf(int $studentId): array;
}
