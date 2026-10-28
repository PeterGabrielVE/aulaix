<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

final readonly class OutcomeSuggestion
{
    /**
     * @param  ?string  $outcome  An Outcome value, or null while something is missing.
     * @param  list<int>  $pendingSubjects
     * @param  list<string>  $missing  What is missing to propose it (EVA-R08.5).
     */
    public function __construct(
        public ?string $outcome,
        public array $pendingSubjects,
        public array $missing,
    ) {}
}
