<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * A literal or a report of a student in a period ('1', '2', '3' or
 * 'final'). Incomplete when the term was closed by exception without it
 * (EVA-R12.3).
 */
final readonly class Mark
{
    public function __construct(
        public string $period,
        public MarkKind $kind,
        public ?Literal $literal,
        public ?string $description,
        public bool $incomplete,
    ) {}

    public static function incomplete(string $period, MarkKind $kind): self
    {
        return new self($period, $kind, null, null, true);
    }
}
