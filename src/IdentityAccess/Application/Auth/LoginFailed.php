<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Why a login was refused, always shown on the email field. Wrong
 * credentials get one generic answer, whether the email has an account or
 * not (IDG-R03.3, S-05).
 */
final class LoginFailed extends BusinessRuleViolation
{
    public static function invalidCredentials(): self
    {
        return self::onField('email', 'Estas credenciales no coinciden con nuestros registros.');
    }

    public static function tooManyAttempts(int $seconds): self
    {
        return self::onField('email', "Demasiados intentos de inicio de sesión. Inténtalo de nuevo en {$seconds} segundos.");
    }

    /**
     * Only said once the password was right (S-05).
     */
    public static function accountNotActive(): self
    {
        return self::onField('email', 'Tu cuenta no está activa.');
    }
}
