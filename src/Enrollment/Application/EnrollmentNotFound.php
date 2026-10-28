<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application;

use AulaX\Shared\Domain\NotFound;

/**
 * Also the answer for an enrollment of another institution: under Row
 * Level Security it does not exist (MAT-R13.3).
 */
final class EnrollmentNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('No encontramos esa inscripción.');
    }
}
