<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The student's age is outside the allowed range of the grade, and no
 * directive approved an exception (MAT-R03.3).
 */
final class AgeOutOfRange extends BusinessRuleViolation
{
    public static function of(int $age, AgeRule $rule): self
    {
        return self::onField(
            'age_exception',
            "Tiene {$age} años y el rango del grado es de {$rule->min} a {$rule->max} años: hace falta la excepción de un directivo.",
        );
    }
}
