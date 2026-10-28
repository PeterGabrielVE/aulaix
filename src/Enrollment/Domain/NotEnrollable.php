<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The student, the year or the section can not take this enrollment
 * (MAT-R01.3 – R01.5, R04.3, R04.5).
 */
final class NotEnrollable extends BusinessRuleViolation
{
    public static function withdrawn(): self
    {
        return self::onField('student', 'El estudiante está retirado: reincorpóralo primero.');
    }

    public static function waitingToGraduate(): self
    {
        return self::onField('student', 'Está pendiente de egresar: solo cursa sus materias pendientes.');
    }

    public static function closedYear(): self
    {
        return self::onField('school_year', 'El año escolar está cerrado.');
    }

    public static function sectionOfAnotherYear(): self
    {
        return self::onField('section', 'La sección no es de ese año escolar.');
    }
}
