<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Hooks;

use AulaX\AcademicStructure\Application\Port\RemovalGuard;
use AulaX\Evaluation\Application\Port\SectionInfo;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\ActivityGradesRepository;

/**
 * A subject with grades in a grade this year does not leave its curriculum
 * (EVA-R09.2, SPEC-008 EAC-R07.3). Enrollments are Enrollment's guard, not
 * this one.
 */
final readonly class SubjectsWithGrades implements RemovalGuard
{
    public function __construct(
        private TeachingFacts $teaching,
        private ActivityGradesRepository $grades,
    ) {}

    public function enrolledIn(int $sectionId): int
    {
        return 0;
    }

    public function hasEnrollments(int $sectionId): bool
    {
        return false;
    }

    public function subjectsInUse(int $schoolYearId, string $grade, array $subjectIds): array
    {
        $sections = array_values(array_map(
            fn (SectionInfo $section) => $section->id,
            array_filter($this->teaching->sectionsOf($schoolYearId), fn (SectionInfo $section) => $section->grade === $grade),
        ));

        return $this->grades->subjectsWithGrades($schoolYearId, $sections, $subjectIds);
    }
}
