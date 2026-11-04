<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application;

use AulaX\Enrollment\Application\Port\PendingSubjectsCheck;
use AulaX\Enrollment\Domain\Enrollment;

/**
 * Whether an enrollment graduates or waits to graduate (MAT-R15), with what
 * the registered PendingSubjectsCheck say: whoever waited to graduate and
 * owes nothing any more graduates (RVP-R05.5). With none registered, every
 * pending subject is owed.
 */
final readonly class Graduation
{
    /**
     * @param  iterable<PendingSubjectsCheck>  $checks
     */
    public function __construct(private iterable $checks = []) {}

    public function graduates(Enrollment $enrollment): bool
    {
        return $enrollment->graduates() || ($enrollment->waitsToGraduate() && ! $this->owesAny($enrollment));
    }

    public function waitsToGraduate(Enrollment $enrollment): bool
    {
        return $enrollment->waitsToGraduate() && $this->owesAny($enrollment);
    }

    private function owesAny(Enrollment $enrollment): bool
    {
        $asked = false;

        foreach ($this->checks as $check) {
            $asked = true;

            if ($check->owesAny($enrollment->studentId(), $enrollment->schoolYearId(), $enrollment->placement()->grade, $enrollment->result()?->pendingSubjects ?? [])) {
                return true;
            }
        }

        return ! $asked;
    }
}
