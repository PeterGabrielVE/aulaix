<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

/**
 * An extension point for SPEC-016 (ADR 0012): whether a student still owes
 * the pending subjects of a result, so whoever waits to graduate graduates
 * once it approved the last one (RVP-R05.5, MAT-R15). Until a module
 * registers one, every pending subject is owed.
 */
interface PendingSubjectsCheck
{
    /**
     * The container tag a module puts its check under.
     */
    public const string TAG = 'enrollment.pending_subjects_checks';

    /**
     * @param  list<int>  $subjectIds  The pending subjects of the result.
     */
    public function owesAny(int $studentId, int $originSchoolYearId, string $originGrade, array $subjectIds): bool;
}
