<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Only a directive annuls an enrollment after 48 hours or with grades
 * (MAT-R14.3).
 */
final class AnnulmentNotAllowed extends BusinessRuleViolation
{
    public static function late(): self
    {
        return self::onField('reason', 'Pasaron más de 48 horas: la anula un directivo.');
    }

    public static function graded(): self
    {
        return self::onField('reason', 'Ya tiene notas: la anula un directivo.');
    }
}
