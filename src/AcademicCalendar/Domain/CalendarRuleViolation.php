<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A rule of the school calendar broken by a request, shown next to the field
 * it concerns (422).
 */
final class CalendarRuleViolation extends BusinessRuleViolation
{
    public static function confirmationMissing(): self
    {
        return self::onField('confirmation', 'Confirma la acción.');
    }
}
