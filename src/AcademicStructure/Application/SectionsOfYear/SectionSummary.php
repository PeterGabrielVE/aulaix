<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\SectionsOfYear;

final readonly class SectionSummary
{
    public function __construct(
        public int $id,
        public string $grade,
        public string $letter,
        public int $capacity,
    ) {}
}
