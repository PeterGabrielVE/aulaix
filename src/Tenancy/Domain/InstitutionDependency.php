<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The administration a public institution depends on (INS-R02.4). Private
 * and subsidised institutions have none: their type already says it.
 */
enum InstitutionDependency: string
{
    case Nacional = 'nacional';
    case Estadal = 'estadal';
    case Municipal = 'municipal';

    /**
     * Empty input means no dependency.
     *
     * @throws BusinessRuleViolation
     */
    public static function fromInput(?string $value): ?self
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return self::tryFrom($value)
            ?? throw BusinessRuleViolation::onField('dependency', 'Elige nacional, estadal o municipal.');
    }
}
