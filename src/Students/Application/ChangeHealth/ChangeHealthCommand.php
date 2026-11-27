<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ChangeHealth;

final readonly class ChangeHealthCommand
{
    public function __construct(
        public int $studentId,
        public ?string $allergies,
        public ?string $medicalConditions,
        public ?string $disability,
    ) {}
}
