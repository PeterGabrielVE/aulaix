<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Marks;

final readonly class SaveLiteralCommand
{
    public function __construct(
        public int $schoolYearId,
        public int $sectionId,
        public int $studentId,
        public int $term,
        public ?string $literal,
        public ?string $description,
    ) {}
}
