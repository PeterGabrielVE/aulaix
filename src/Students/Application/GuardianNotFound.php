<?php

declare(strict_types=1);

namespace AulaX\Students\Application;

use AulaX\Shared\Domain\NotFound;

/**
 * Also the answer for a guardian of another institution: under Row Level
 * Security it does not exist (EST-R11.1).
 */
final class GuardianNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('No encontramos ese representante.');
    }
}
