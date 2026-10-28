<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Roster;

final readonly class PromotionRow
{
    /**
     * @param  ?string  $outcome  Null while it has no result.
     * @param  list<int>  $pendingSubjects
     */
    public function __construct(
        public int $enrollmentId,
        public string $fullName,
        public string $identification,
        public ?string $outcome,
        public array $pendingSubjects,
    ) {}
}
