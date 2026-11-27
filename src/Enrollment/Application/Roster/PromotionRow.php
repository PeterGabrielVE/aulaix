<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

use AulaX\Enrollment\Application\Port\OutcomeSuggestion;

final readonly class PromotionRow
{
    /**
     * @param  ?string  $outcome  Null while it has no result.
     * @param  list<int>  $pendingSubjects
     * @param  ?OutcomeSuggestion  $proposal  From the grades (SPEC-011), when a module proposes.
     */
    public function __construct(
        public int $enrollmentId,
        public string $fullName,
        public string $identification,
        public ?string $outcome,
        public array $pendingSubjects,
        public ?OutcomeSuggestion $proposal = null,
    ) {}
}
