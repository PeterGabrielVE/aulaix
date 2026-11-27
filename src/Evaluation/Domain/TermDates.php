<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;

/**
 * The first and last day of a term (SPEC-007).
 */
final readonly class TermDates
{
    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {}

    public function contains(DateTimeImmutable $date): bool
    {
        return $date >= $this->start && $date <= $this->end;
    }

    public function display(): string
    {
        return "{$this->start->format('d/m/Y')} – {$this->end->format('d/m/Y')}";
    }
}
