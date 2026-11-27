<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Hooks;

use AulaX\Enrollment\Application\Port\PendingSubjectsCheck;
use AulaX\Evaluation\Application\PendingGroups\PendingMembers;
use AulaX\Evaluation\Domain\OwedPending;

/**
 * Whether a student still owes the pending subjects of a result (SPEC-010),
 * so whoever waited to graduate graduates once it approved the last one
 * (RVP-R05.5): a pending subject is owed until its group of a later year
 * approves it, after its revision and with the third term closed.
 */
final readonly class OwedPendingsCheck implements PendingSubjectsCheck
{
    public function __construct(private PendingMembers $members) {}

    public function owesAny(int $studentId, int $originSchoolYearId, string $originGrade, array $subjectIds): bool
    {
        foreach ($subjectIds as $subjectId) {
            if (! $this->members->isApproved(new OwedPending($studentId, $subjectId, $originGrade, $originSchoolYearId))) {
                return true;
            }
        }

        return false;
    }
}
