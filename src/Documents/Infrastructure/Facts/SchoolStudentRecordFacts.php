<?php

declare(strict_types=1);

namespace AulaX\Documents\Infrastructure\Facts;

use AulaX\Documents\Application\Port\StudentRecordFacts;
use AulaX\Enrollment\Application\Roster\DocumentEnrollmentView;
use AulaX\Enrollment\Application\Roster\StudentEnrollmentsForDocumentsQuery;
use AulaX\Students\Application\StudentForDocuments\StudentForDocumentsQuery;
use Collator;

/**
 * Reads student records, enrollments, withdrawals and section eligibility
 * through the public Application queries of Students and Enrollment (A-06).
 */
final readonly class SchoolStudentRecordFacts implements StudentRecordFacts
{
    public function __construct(
        private StudentForDocumentsQuery $students,
        private StudentEnrollmentsForDocumentsQuery $enrollments,
    ) {}

    public function student(int $studentId): ?array
    {
        $profile = ($this->students)($studentId);

        if ($profile === null) {
            return null;
        }

        return [
            'id' => $profile->id,
            'givenNames' => $profile->givenNames,
            'familyNames' => $profile->familyNames,
            'fullName' => $profile->fullName,
            'identification' => $profile->identification,
            'status' => $profile->status,
            'latestWithdrawalDate' => $profile->latestWithdrawalDate,
            'latestWithdrawalReason' => $profile->latestWithdrawalReason,
        ];
    }

    public function enrollmentsOf(int $studentId): array
    {
        return array_map(
            fn (DocumentEnrollmentView $entry) => [
                'enrollmentId' => $entry->enrollmentId,
                'schoolYearId' => $entry->schoolYearId,
                'sectionId' => $entry->sectionId,
                'gradeCode' => $entry->gradeCode,
                'gradeName' => $entry->gradeName,
                'level' => $entry->level,
                'sectionLetter' => $entry->sectionLetter,
                'startsOn' => $entry->startsOn,
                'endsOn' => $entry->endsOn,
                'endReason' => $entry->endReason,
                'isCurrent' => $entry->isCurrent,
                'outcome' => $entry->outcome,
                'outcomeLabel' => $entry->outcomeLabel,
                'waitsToGraduate' => $entry->waitsToGraduate,
                'graduated' => $entry->graduated,
            ],
            $this->enrollments->forStudent($studentId),
        );
    }

    public function eligibleSectionStudents(int $schoolYearId, int $sectionId, ?string $termEndDate): array
    {
        $eligible = $this->enrollments->eligibleInSection($schoolYearId, $sectionId, $termEndDate);
        $rows = [];

        foreach ($eligible as $entry) {
            $profile = ($this->students)($entry->studentId);

            if ($profile === null) {
                continue;
            }

            $rows[] = [
                'studentId' => $profile->id,
                'givenNames' => $profile->givenNames,
                'familyNames' => $profile->familyNames,
                'fullName' => $profile->fullName,
                'identification' => $profile->identification,
                'outcome' => $entry->outcome,
                'outcomeLabel' => $entry->outcomeLabel,
                'graduated' => $entry->graduated,
            ];
        }

        $collator = new Collator('es');
        usort($rows, fn (array $a, array $b) => $collator->compare(
            "{$a['familyNames']} {$a['givenNames']}",
            "{$b['familyNames']} {$b['givenNames']}",
        ));

        return $rows;
    }
}
