<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A result of the year that the rules of the level, or the moment, do not
 * allow (MAT-R06.2 – R06.7, R06.9).
 */
final class ResultNotAllowed extends BusinessRuleViolation
{
    public static function outcome(string $message): self
    {
        return self::onField('outcome', $message);
    }

    public static function pendingSubjects(string $message): self
    {
        return self::onField('pending_subjects', $message);
    }
}
