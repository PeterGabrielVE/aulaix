<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

use AulaX\Shared\Domain\NotFound;

/**
 * Also the answer for a section of another institution or another year.
 */
final class SectionNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('No encontramos esa sección.');
    }
}
