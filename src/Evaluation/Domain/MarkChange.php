<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * What a literal or a report changed, for the audit log: the literal, and
 * only whether a text was written, never the text (EVA-R10.2).
 */
final readonly class MarkChange
{
    public function __construct(
        public string $field,
        public ?string $before,
        public ?string $after,
    ) {}
}
