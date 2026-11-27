<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Domain;

/**
 * A coordinator of a level in a school year, for the whole level or for some
 * of its grades (EAC-R06.3).
 */
final readonly class CoordinatorAssignment
{
    /**
     * @param  list<Grade>  $grades  Empty: the whole level.
     */
    public function __construct(
        public int $accountId,
        public string $level,
        public array $grades = [],
    ) {}
}
