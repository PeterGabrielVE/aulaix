<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The grade of a student in a term: a whole number, incomplete while an
 * activity lacks its grade (EVA-R03.2), or not applicable when none of its
 * activities applies (EVA-R03.7).
 */
final readonly class TermResult
{
    private function __construct(
        public string $state,
        public ?int $score,
    ) {}

    public static function scored(int $score): self
    {
        return new self('scored', $score);
    }

    public static function incomplete(): self
    {
        return new self('incomplete', null);
    }

    public static function notApplicable(): self
    {
        return new self('not_applicable', null);
    }

    public function isScored(): bool
    {
        return $this->state === 'scored';
    }

    public function isIncomplete(): bool
    {
        return $this->state === 'incomplete';
    }
}
