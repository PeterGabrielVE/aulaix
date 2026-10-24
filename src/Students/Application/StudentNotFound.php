<?php

declare(strict_types=1);

namespace AulaX\Students\Application;

use AulaX\Shared\Domain\NotFound;

/**
 * Also the answer for a student of another institution, or one a guardian
 * does not represent: for them it does not exist (EST-R08.3, R11.1).
 */
final class StudentNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('No encontramos ese estudiante.');
    }
}
