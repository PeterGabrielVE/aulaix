<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Hooks;

use AulaX\Enrollment\Application\Port\GradedEnrollment;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\ActivityGradesRepository;
use AulaX\Evaluation\Domain\Mark;
use AulaX\Evaluation\Domain\StudentMarksRepository;

/**
 * Whether an enrollment has grades, literals or reports (EVA-R09.1), so
 * only a manager annuls it (SPEC-010 MAT-R14.3): in Media General, a grade
 * or NP in an activity of its section while it lasted; in Primaria and
 * Inicial, a literal or report of the student that year that is not just
 * the incomplete left by a closing.
 */
final readonly class GradedEnrollmentCheck implements GradedEnrollment
{
    public function __construct(
        private EnrollmentFacts $enrollments,
        private TeachingFacts $teaching,
        private ActivityGradesRepository $grades,
        private StudentMarksRepository $marks,
    ) {}

    public function hasGrades(int $enrollmentId): bool
    {
        $enrollment = $this->enrollments->enrollment($enrollmentId);

        if ($enrollment === null) {
            return false;
        }

        $period = $enrollment['period'];
        $level = $this->teaching->section($enrollment['schoolYearId'], $period->sectionId)?->level;

        if ($level === 'media_general') {
            return $this->grades->gradedWithin($enrollment['studentId'], $period->sectionId, $period->startsOn, $period->endsOn);
        }

        $marks = $this->marks->forStudents($enrollment['schoolYearId'], [$enrollment['studentId']])[$enrollment['studentId']];

        return array_filter($marks->marks(), fn (Mark $mark) => ! $mark->incomplete) !== [];
    }
}
