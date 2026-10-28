<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class SectionOverview
{
    public function __construct(
        public int $id,
        public string $grade,
        public string $gradeName,
        public string $letter,
        public int $capacity,
        public int $enrolled,
        public bool $overCapacity,
    ) {}
}
