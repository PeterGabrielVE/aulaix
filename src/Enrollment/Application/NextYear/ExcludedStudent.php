<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\NextYear;

final readonly class ExcludedStudent
{
    public function __construct(
        public int $studentId,
        public string $fullName,
        public string $reason,
    ) {}
}
