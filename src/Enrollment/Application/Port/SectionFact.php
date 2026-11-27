<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

final readonly class SectionFact
{
    public function __construct(
        public int $id,
        public int $schoolYearId,
        public string $grade,
        public string $gradeName,
        public string $level,
        public string $letter,
        public int $capacity,
    ) {}
}
