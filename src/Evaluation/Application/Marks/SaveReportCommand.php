<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Marks;

final readonly class SaveReportCommand
{
    public function __construct(
        public int $schoolYearId,
        public int $sectionId,
        public int $studentId,
        public int $term,
        public string $text,
    ) {}
}
