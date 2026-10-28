<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The section is full and no reason to go over it was given (MAT-R02.1).
 */
final class OverCapacity extends BusinessRuleViolation
{
    public static function of(Occupancy $occupancy): self
    {
        return self::onField(
            'over_capacity_reason',
            "La sección tiene {$occupancy->enrolled} inscritos y cupo {$occupancy->capacity}: indica el motivo para superarlo.",
        );
    }
}
