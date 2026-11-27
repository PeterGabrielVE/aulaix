<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

use AulaX\Shared\Domain\BusinessRuleViolation;

final class ConsolidationRejected extends BusinessRuleViolation
{
    public static function invalidCode(): self
    {
        return self::onField('code', 'El código no es válido o ya venció. Pide uno nuevo.');
    }

    /**
     * Regular accounts, and those marked for review: only support resolves
     * the latter (IDG-R11.8).
     */
    public static function notAvailable(): self
    {
        return self::onField('code', 'Tus cuentas no se pueden unificar desde aquí. Si crees que es un error, contacta a soporte.');
    }
}
