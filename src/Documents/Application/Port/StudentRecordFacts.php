<?php

declare(strict_types=1);

namespace AulaX\Documents\Application\Port;

/**
 * Student identity, enrollments, withdrawals and progression/graduation state
 * from Students and Enrollment (A-06).
 */
interface StudentRecordFacts
{
    /**
     * @return ?array{id: int, givenNames: string, familyNames: string, fullName: string, identification: string, status: string, latestWithdrawalDate: ?string, latestWithdrawalReason: ?string}
     */
    public function student(int $studentId): ?array;

    /**
     * Non-annulled enrollments of the student, oldest first.
     *
     * @return list<array{enrollmentId: int, schoolYearId: int, sectionId: int, gradeCode: string, gradeName: string, level: string, sectionLetter: string, startsOn: string, endsOn: ?string, endReason: ?string, isCurrent: bool, outcome: ?string, outcomeLabel: ?string, waitsToGraduate: bool, graduated: bool}>
     */
    public function enrollmentsOf(int $studentId): array;

    /**
     * Eligible students of a section for a closed term (or final report when
     * $termEndDate is null — using third term end date), ordered alphabetically
     * by family names and given names (DOC-R01.6, R03.1).
     *
     * @return list<array{studentId: int, givenNames: string, familyNames: string, fullName: string, identification: string, outcome: ?string, outcomeLabel: ?string, graduated: bool}>
     */
    public function eligibleSectionStudents(int $schoolYearId, int $sectionId, ?string $termEndDate): array;
}
