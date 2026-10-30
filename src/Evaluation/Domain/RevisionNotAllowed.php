<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A revision that can not be recorded (RVP-R01.4, R01.5, R01.7).
 */
final class RevisionNotAllowed extends BusinessRuleViolation
{
    public static function notFailed(int $studentId): self
    {
        return self::onField("revisions.{$studentId}", 'La definitiva no está reprobada: no lleva revisión.');
    }

    public static function tooEarly(): self
    {
        return self::onField('term', 'La revisión se registra con el tercer lapso cerrado.');
    }

    public static function resultRecorded(int $studentId): self
    {
        return self::onField("revisions.{$studentId}", 'El estudiante ya tiene su resultado del año: corrígelo primero en Prosecución.');
    }
}
