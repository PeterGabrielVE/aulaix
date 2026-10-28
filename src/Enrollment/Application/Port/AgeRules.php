<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

use AulaX\Enrollment\Domain\AgeRule;

/**
 * The ages of each grade and the cutoff date (MAT-R03.6): configuration of
 * the platform, not code.
 */
interface AgeRules
{
    /**
     * Month and day of the cutoff, as "MM-DD".
     */
    public function cutoff(): string;

    /**
     * Null when the grade has no ages (MAT-R03.7).
     */
    public function of(string $grade): ?AgeRule;
}
