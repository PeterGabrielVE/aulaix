<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The invitation is valid, but its email already has an account: it is
 * accepted from that account, once logged in (IDG-R04.5). Only the holder of
 * a valid link ever gets this answer, so it reveals nothing to anybody else
 * (IDG-NF02).
 */
final class AccountAlreadyExists extends BusinessRuleViolation
{
    public static function create(): self
    {
        return self::onField('email', 'Ya tienes una cuenta en AulaX con este correo: inicia sesión para aceptar la invitación.');
    }
}
