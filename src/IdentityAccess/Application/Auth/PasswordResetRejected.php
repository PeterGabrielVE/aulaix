<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The same answer for a wrong, expired or used link, or an unknown email.
 */
final class PasswordResetRejected extends BusinessRuleViolation
{
    public static function create(): self
    {
        return self::onField('email', 'El enlace no es válido o ha vencido. Solicita uno nuevo.');
    }
}
