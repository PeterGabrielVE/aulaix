<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The new plan leaves out activities that have grades (EVA-R01.5).
 */
final class RemovalNeedsConfirmation extends BusinessRuleViolation
{
    public static function of(int $count): self
    {
        return self::onField('confirm_removals', $count === 1
            ? 'Quitarás 1 actividad con notas: confirma para quitarla con sus notas.'
            : "Quitarás {$count} actividades con notas: confirma para quitarlas con sus notas.");
    }
}
