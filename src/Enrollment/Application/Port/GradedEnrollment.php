<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

/**
 * An extension point for SPEC-011 (ADR 0012): whether an enrollment has
 * grades, so only a directive annuls it (MAT-R14.3). Until a module
 * registers one, no enrollment has grades.
 */
interface GradedEnrollment
{
    /**
     * The container tag a module puts its check under.
     */
    public const string TAG = 'enrollment.graded_enrollments';

    public function hasGrades(int $enrollmentId): bool;
}
