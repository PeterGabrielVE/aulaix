<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Account;

use AulaX\Shared\Domain\BusinessRuleViolation;

final class InvalidEmailAddress extends BusinessRuleViolation
{
    public static function of(string $email): self
    {
        return self::onField('email', 'El correo electrónico no es válido.');
    }
}
