<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use InvalidArgumentException;

/**
 * The ages of a grade (MAT-R03): the regular one, and the range a student
 * enrolls in without an exception.
 */
final readonly class AgeRule
{
    public function __construct(
        public string $grade,
        public int $regular,
        public int $min,
        public int $max,
    ) {
        if ($min < 0 || $min > $regular || $regular > $max) {
            throw new InvalidArgumentException("The ages of {$grade} must keep min <= regular <= max.");
        }
    }
}
