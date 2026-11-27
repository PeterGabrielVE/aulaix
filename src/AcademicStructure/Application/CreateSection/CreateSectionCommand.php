<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\CreateSection;

final readonly class CreateSectionCommand
{
    public function __construct(
        public int $schoolYearId,
        public ?string $grade,
        public ?string $letter,
        public mixed $capacity,
        public ?string $capacityReason,
    ) {}
}
