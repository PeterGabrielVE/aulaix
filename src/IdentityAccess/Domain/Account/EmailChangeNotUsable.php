<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The same answer for a wrong, expired or already used verification link.
 */
final class EmailChangeNotUsable extends BusinessRuleViolation
{
    public static function create(): self
    {
        return self::onField('email', 'El enlace para confirmar tu nuevo correo no es válido o ya venció. Pide el cambio otra vez.');
    }
}
