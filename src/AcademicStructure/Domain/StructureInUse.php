<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

use AulaX\Shared\Domain\Conflict;

/**
 * A section with students or a subject with grades can not be removed from
 * an active year (EAC-R07.3).
 */
final class StructureInUse extends Conflict
{
    public static function section(): self
    {
        return new self('La sección tiene estudiantes inscritos.');
    }

    public static function subject(): self
    {
        return new self('La materia ya tiene notas.');
    }
}
