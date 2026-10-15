<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Auth;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * The invitation is valid, but its email already has an account. Accepting
 * it from that account needs the central login (SPEC-004 PR B); until then
 * the link only creates new accounts. Only the holder of a valid link ever
 * gets this answer, so it reveals nothing to anybody else (IDG-NF02).
 */
final class AccountAlreadyExists extends BusinessRuleViolation
{
    public static function create(): self
    {
        return self::onField('email', 'Ya tienes una cuenta en AulaX con este correo. Pronto podrás aceptar la invitación iniciando sesión con ella.');
    }
}
