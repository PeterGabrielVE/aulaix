<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\RecordResult;

final readonly class RecordResultCommand
{
    /**
     * @param  ?string  $outcome  promoted, repeats, promoted_with_pending or lost_by_absence.
     * @param  list<int>  $pendingSubjects  Subject ids, only when promoted with pending subjects.
     * @param  int  $actorId  The account signed in.
     */
    public function __construct(
        public int $enrollmentId,
        public ?string $outcome,
        public array $pendingSubjects,
        public int $actorId,
    ) {}
}
