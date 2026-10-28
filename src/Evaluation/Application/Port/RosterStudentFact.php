<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Port;

final readonly class RosterStudentFact
{
    public function __construct(
        public int $studentId,
        public string $fullName,
        public string $identification,
    ) {}
}
