<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use InvalidArgumentException;

/**
 * To the nearest whole number with ,5 up (EVA-R03.1), on an exact fraction
 * of integers (EVA-NF03): never a float, so 9,5 is always 10.
 */
final class Rounding
{
    public static function nearest(int $numerator, int $denominator): int
    {
        if ($denominator <= 0 || $numerator < 0) {
            throw new InvalidArgumentException('Only non negative fractions with a positive denominator are rounded.');
        }

        return intdiv(2 * $numerator + $denominator, 2 * $denominator);
    }
}
