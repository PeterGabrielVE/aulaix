<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The teacher of a group of pending subjects is an active member with the
 * role Docente (RVP-R03.5).
 */
final class TeacherNotEligible extends BusinessRuleViolation
{
    public static function create(): self
    {
        return self::onField('teacher', 'Solo se asigna a un miembro activo con el rol Docente.');
    }
}
