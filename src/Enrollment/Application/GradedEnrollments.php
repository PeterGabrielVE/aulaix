<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application;

use AulaX\Enrollment\Application\Port\GradedEnrollment;

/**
 * Every registered GradedEnrollment, asked together.
 */
final readonly class GradedEnrollments
{
    /**
     * @param  iterable<GradedEnrollment>  $checks
     */
    public function __construct(private iterable $checks) {}

    public function hasGrades(int $enrollmentId): bool
    {
        foreach ($this->checks as $check) {
            if ($check->hasGrades($enrollmentId)) {
                return true;
            }
        }

        return false;
    }
}
