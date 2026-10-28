<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Marks;

final readonly class SaveFinalLiteralCommand
{
    public function __construct(
        public int $schoolYearId,
        public int $sectionId,
        public int $studentId,
        public ?string $literal,
        public ?string $description,
    ) {}
}
