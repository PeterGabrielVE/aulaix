<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * An activity of the term as it counts for one student: its weight,
 * whether it applies to it, and what it got.
 */
final readonly class ScoredActivity
{
    public function __construct(
        public Weight $weight,
        public bool $applies,
        public ?GradeMark $mark,
    ) {}
}
