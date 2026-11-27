<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application;

use AulaX\Shared\Domain\BusinessRuleViolation;
use DateTimeImmutable;

/**
 * A date as a form sends it (Y-m-d).
 */
final class EnrollmentDate
{
    /**
     * @throws BusinessRuleViolation
     */
    public static function parse(?string $value, string $field = 'date'): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim((string) $value));

        if ($date === false || $date->format('Y-m-d') !== trim((string) $value)) {
            throw BusinessRuleViolation::onField($field, 'Indica una fecha válida.');
        }

        return $date;
    }
}
