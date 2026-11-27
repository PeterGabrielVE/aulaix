<?php

declare(strict_types=1);

namespace AulaX\Students\Application\ShowStudent;

/**
 * Only for whoever manages students (EST-R04.2).
 */
final readonly class HealthView
{
    public function __construct(
        public ?string $allergies,
        public ?string $medicalConditions,
        public ?string $disability,
    ) {}
}
