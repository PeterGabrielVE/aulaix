<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Shared\Domain\NotFound;

/**
 * Also the answer for a section of another year or institution.
 */
final class SectionNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('No encontramos esa sección.');
    }
}
