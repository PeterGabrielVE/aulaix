<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\Conflict;

/**
 * A student has one current enrollment per school year (MAT-R01.2, NF03).
 */
final class AlreadyEnrolled extends Conflict
{
    public static function in(?string $gradeName, ?string $letter): self
    {
        return new self($gradeName === null
            ? 'El estudiante ya está inscrito en este año escolar.'
            : "El estudiante ya está inscrito en {$gradeName} sección {$letter} en este año escolar.");
    }
}
