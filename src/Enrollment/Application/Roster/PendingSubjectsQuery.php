<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Application\Port\StudentFacts;
use AulaX\Enrollment\Domain\Enrollment;

/**
 * The public API for SPEC-016 (RVP-R03.1 – R03.3): the pending subjects
 * from the results of the years before a school year, with the grade where
 * each was failed, of the students enrolled in that year or waiting to
 * graduate (MAT-R15.2). Whoever left the institution is not there. Which of
 * them were approved since is Evaluation's to say; who may see them, the
 * caller's.
 */
final readonly class PendingSubjectsQuery
{
    public function __construct(
        private EnrollmentList $enrollments,
        private StudentFacts $students,
    ) {}

    /**
     * @return list<PendingSubjectView>
     */
    public function __invoke(int $schoolYearId): array
    {
        $results = array_values(array_filter(
            $this->enrollments->withPendingSubjects(),
            fn (Enrollment $enrollment) => $enrollment->schoolYearId() !== $schoolYearId,
        ));

        if ($results === []) {
            return [];
        }

        $current = array_flip($this->enrollments->currentStudentsOf($schoolYearId));
        $students = $this->students->byIds(array_values(array_unique(array_map(fn (Enrollment $enrollment) => $enrollment->studentId(), $results))));
        $views = [];

        foreach ($results as $enrollment) {
            $studentId = $enrollment->studentId();
            $takesThem = isset($current[$studentId]) || ($enrollment->waitsToGraduate() && ($students[$studentId]->active ?? false));

            if (! $takesThem) {
                continue;
            }

            foreach ($enrollment->result()?->pendingSubjects ?? [] as $subjectId) {
                $views[] = new PendingSubjectView($studentId, $subjectId, $enrollment->placement()->grade, $enrollment->schoolYearId());
            }
        }

        return $views;
    }
}
