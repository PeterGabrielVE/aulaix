<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;

/**
 * An evaluation of a plan: its name, date and weight (EVA-R01.1).
 */
final readonly class Activity
{
    public function __construct(
        public ?ActivityId $id,
        public string $name,
        public DateTimeImmutable $date,
        public Weight $weight,
    ) {}
}
