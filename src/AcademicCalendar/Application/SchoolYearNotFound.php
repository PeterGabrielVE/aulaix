<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application;

use AulaX\Shared\Domain\NotFound;

/**
 * Also the answer for a year of another institution: under Row Level
 * Security it does not exist (CAL-R08.1).
 */
final class SchoolYearNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('El año escolar no existe.');
    }
}
