<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * Why a case was not resolved. Nothing changed (IDG-R11.3, R11.5, R11.6).
 */
final class CaseResolutionRefused extends BusinessRuleViolation
{
    public static function operatorNotAuthorized(): self
    {
        return new self('El operador no está autorizado para resolver consolidaciones.');
    }

    public static function missing(string $what): self
    {
        return new self("Falta {$what}.");
    }

    public static function notUnderReview(): self
    {
        return new self('Las cuentas indicadas no son todas las de un caso en revisión, o su estado cambió desde que se marcaron.');
    }
}
