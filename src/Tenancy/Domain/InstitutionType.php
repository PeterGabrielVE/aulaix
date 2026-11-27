<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Public, private or subsidised (INS-R02.1). Which modules each kind turns
 * on is decided by the spec of each module (F1-09).
 */
enum InstitutionType: string
{
    case Publica = 'publica';
    case Privada = 'privada';
    case Subvencionada = 'subvencionada';

    /**
     * @throws BusinessRuleViolation INS-R02.2
     */
    public static function fromInput(string $value): self
    {
        return self::tryFrom($value)
            ?? throw BusinessRuleViolation::onField('type', 'Elige si el plantel es público, privado o subvencionado.');
    }
}
