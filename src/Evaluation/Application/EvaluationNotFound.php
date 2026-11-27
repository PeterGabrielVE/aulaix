<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application;

use AulaX\Shared\Domain\NotFound;

/**
 * Also the answer for a section, activity or plan of another institution,
 * or one the person may not see (EVA-R07.6, R11.3).
 */
final class EvaluationNotFound extends NotFound
{
    public static function create(): self
    {
        return new self('No encontramos esa planilla.');
    }
}
