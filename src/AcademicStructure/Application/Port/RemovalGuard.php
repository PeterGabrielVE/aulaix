<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Port;

/**
 * An extension point for the modules that build on the structure: SPEC-010
 * (enrollments) and SPEC-011 (grades) register theirs under TAG, so a
 * section with students or a subject with grades is not removed from an
 * active year (EAC-R07.3) and a capacity never drops below the enrolled
 * students (EAC-R02.6). Until they exist, nothing is in use.
 */
interface RemovalGuard
{
    /**
     * The container tag a module puts its guard under.
     */
    public const string TAG = 'academic_structure.removal_guards';

    /**
     * How many students are enrolled in the section.
     */
    public function enrolledIn(int $sectionId): int;

    /**
     * Whether the section has any enrollment, current, ended or annulled:
     * its history keeps it (SPEC-010 MAT-R10.1).
     */
    public function hasEnrollments(int $sectionId): bool;

    /**
     * Which of these subjects of the grade have grades this year.
     *
     * @param  list<int>  $subjectIds
     * @return list<int>
     */
    public function subjectsInUse(int $schoolYearId, string $grade, array $subjectIds): array;
}
