<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Sheets;

final readonly class SheetEntry
{
    public function __construct(
        public int $sectionId,
        public string $section,
        public int $subjectId,
        public string $subject,
        public bool $teaches,
    ) {}
}
