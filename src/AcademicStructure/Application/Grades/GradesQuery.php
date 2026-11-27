<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Grades;

use AulaX\AcademicStructure\Domain\Grade;

/**
 * The public API for SPEC-010: the official grades in their order, with
 * their level, so promotion knows which grade comes next.
 */
final readonly class GradesQuery
{
    /**
     * @return list<GradeView>
     */
    public function __invoke(): array
    {
        return array_map(
            fn (Grade $grade) => new GradeView($grade->value, $grade->level(), $grade->name()),
            Grade::cases(),
        );
    }
}
