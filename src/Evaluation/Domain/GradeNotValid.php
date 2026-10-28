<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A grade outside the scale (EVA-R02.2), or one a student can not take.
 */
final class GradeNotValid extends BusinessRuleViolation
{
    public static function outOfScale(string $field = 'grade'): self
    {
        return self::onField($field, 'La nota debe estar entre 1 y 20, con hasta dos decimales.');
    }

    public static function notInSection(int $studentId): self
    {
        return self::onField("grades.{$studentId}", 'El estudiante no tiene inscripción vigente en la sección.');
    }

    public static function notApplicable(int $studentId): self
    {
        return self::onField("grades.{$studentId}", 'La actividad es anterior a la incorporación del estudiante: no aplica.');
    }
}
