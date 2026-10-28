<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Hooks;

use AulaX\AcademicStructure\Application\Port\RemovalGuard;
use AulaX\Enrollment\Domain\EnrollmentRepository;

/**
 * AcademicStructure's extension point (ADR 0012): a section keeps every
 * enrollment it ever had (MAT-R10.1), and its capacity never drops below
 * its current ones (MAT-R10.2). Grades are SPEC-011's to tell.
 */
final readonly class EnrollmentRemovalGuard implements RemovalGuard
{
    public function __construct(private EnrollmentRepository $enrollments) {}

    public function enrolledIn(int $sectionId): int
    {
        return $this->enrollments->currentCountIn($sectionId);
    }

    public function hasEnrollments(int $sectionId): bool
    {
        return $this->enrollments->hasAnyIn($sectionId);
    }

    public function subjectsInUse(int $schoolYearId, string $grade, array $subjectIds): array
    {
        return [];
    }
}
