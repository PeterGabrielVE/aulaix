<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

/**
 * How a student's age fits a grade (MAT-R03.2, R03.3, R03.7).
 */
enum AgeFit: string
{
    case Regular = 'regular';
    case Warning = 'warning';
    case OutOfRange = 'out_of_range';
    case Unchecked = 'unchecked';
}
