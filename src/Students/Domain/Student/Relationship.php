<?php

declare(strict_types=1);

namespace AulaX\Students\Domain\Student;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * What a guardian is of the student (EST-R03.1).
 */
enum Relationship: string
{
    case Mother = 'mother';
    case Father = 'father';
    case Other = 'other';

    /**
     * @throws BusinessRuleViolation
     */
    public static function fromInput(string $value, string $field = 'relationship'): self
    {
        return self::tryFrom($value) ?? throw BusinessRuleViolation::onField($field, 'Elige madre, padre u otro.');
    }
}
