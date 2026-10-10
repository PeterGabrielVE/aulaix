<?php

declare(strict_types=1);

namespace AulaX\Catalogs\Domain;

use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * A reference catalog that breaks one of its invariants. Raised before any
 * write, so a broken source never leaves the catalog half synchronized.
 */
final class InvalidCatalog extends BusinessRuleViolation
{
    public static function invalidCode(string $code): self
    {
        return new self("Código de catálogo inválido: «{$code}».");
    }

    public static function duplicateCode(string $code): self
    {
        return new self("Código de catálogo repetido: {$code}.");
    }

    public static function missingName(string $code): self
    {
        return new self("La entrada {$code} no tiene nombre.");
    }

    public static function emptyBranch(string $code): self
    {
        return new self("La entrada {$code} no tiene elementos hijos.");
    }

    public static function misplacedChild(string $child, string $parent): self
    {
        return new self("El código {$child} no pertenece a {$parent}.");
    }

    public static function placeholderCode(string $code): self
    {
        return new self("El código {$code} pertenece al catálogo provisional de la primera versión.");
    }

    public static function levelWithoutSubjects(EducationLevel $level): self
    {
        return new self("El nivel {$level->value} no tiene materias.");
    }
}
