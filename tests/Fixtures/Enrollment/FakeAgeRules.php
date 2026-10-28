<?php

namespace Tests\Fixtures\Enrollment;

use AulaX\Enrollment\Application\Port\AgeRules;
use AulaX\Enrollment\Domain\AgeRule;

/**
 * The ages of Primaria, as decided for SPEC-010; none for the other grades.
 */
final class FakeAgeRules implements AgeRules
{
    public function cutoff(): string
    {
        return '12-31';
    }

    public function of(string $grade): ?AgeRule
    {
        if (! str_starts_with($grade, 'primaria_')) {
            return null;
        }

        $regular = 5 + (int) substr($grade, -1);

        return new AgeRule($grade, $regular, $regular - 1, $regular + 1);
    }
}
