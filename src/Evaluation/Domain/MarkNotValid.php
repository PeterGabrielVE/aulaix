<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A literal or a report that can not be recorded (EVA-R04, R05).
 */
final class MarkNotValid extends BusinessRuleViolation
{
    public static function literal(): self
    {
        return self::onField('literal', 'El literal debe ser A, B, C, D o E.');
    }

    public static function descriptionTooLong(): self
    {
        return self::onField('description', 'La descripción tiene como máximo 5000 caracteres.');
    }

    public static function reportLength(): self
    {
        return self::onField('report', 'El informe debe tener entre 1 y 5000 caracteres.');
    }

    public static function finalTooEarly(): self
    {
        return self::onField('period', 'El literal final se registra con el tercer lapso cerrado.');
    }

    public static function wrongLevel(MarkKind $kind): self
    {
        return self::onField('period', $kind === MarkKind::Literal
            ? 'Los literales son de Primaria.'
            : 'Los informes son de Educación Inicial.');
    }

    public static function notInSection(): self
    {
        return self::onField('student', 'El estudiante no tiene inscripción vigente en la sección.');
    }
}
