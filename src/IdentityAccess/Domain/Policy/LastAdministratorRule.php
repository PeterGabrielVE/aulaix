<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Domain\Policy;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * An institution must always keep an active Administrador: only an
 * Administrador can grant that role (IAM-R10.1), so losing the last one would
 * lock the institution out of its own administration. Checked when a member
 * leaves, is suspended, loses roles or deletes their account (IDG-R07.3,
 * IDG-R08.2).
 */
final class LastAdministratorRule
{
    public static function check(int $activeAdministrators, bool $removesAnActiveAdministrador): ?BusinessRuleViolation
    {
        if ($removesAnActiveAdministrador && $activeAdministrators <= 1) {
            return BusinessRuleViolation::onField('roles', 'El plantel debe tener al menos un Administrador activo.');
        }

        return null;
    }
}
