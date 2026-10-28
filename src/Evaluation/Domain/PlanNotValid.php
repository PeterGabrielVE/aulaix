<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A plan of evaluation that breaks its rules (EVA-R01.2, R01.3).
 */
final class PlanNotValid extends BusinessRuleViolation
{
    public static function sum(int $hundredths): self
    {
        return self::onField('activities', 'Los porcentajes suman '.Hundredths::display($hundredths).' %: deben sumar 100 %.');
    }

    public static function name(int $position): self
    {
        return self::onField('activities', "La actividad {$position} necesita un nombre.");
    }

    public static function weight(int $position): self
    {
        return self::onField('activities', "El porcentaje de la actividad {$position} debe ser mayor que 0 y de hasta 100, con hasta dos decimales.");
    }

    public static function date(int $position, TermDates $term): self
    {
        return self::onField('activities', "La fecha de la actividad {$position} debe estar dentro del lapso ({$term->display()}).");
    }
}
