<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application;

use AulaX\AcademicStructure\Application\Port\RemovalGuard;
use AulaX\AcademicStructure\Domain\Grade;

/**
 * Every registered RemovalGuard, asked together.
 */
final readonly class RemovalGuards
{
    /**
     * @param  iterable<RemovalGuard>  $guards
     */
    public function __construct(private iterable $guards) {}

    public function enrolledIn(int $sectionId): int
    {
        $enrolled = 0;

        foreach ($this->guards as $guard) {
            $enrolled = max($enrolled, $guard->enrolledIn($sectionId));
        }

        return $enrolled;
    }

    public function sectionInUse(int $sectionId): bool
    {
        return $this->enrolledIn($sectionId) > 0;
    }

    public function hasEnrollments(int $sectionId): bool
    {
        foreach ($this->guards as $guard) {
            if ($guard->hasEnrollments($sectionId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $subjectIds
     * @return list<int>
     */
    public function subjectsInUse(int $schoolYearId, Grade $grade, array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        $inUse = [];

        foreach ($this->guards as $guard) {
            $inUse = [...$inUse, ...$guard->subjectsInUse($schoolYearId, $grade->value, $subjectIds)];
        }

        return array_values(array_unique($inUse));
    }
}
