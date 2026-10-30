<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Evaluation\Domain\FinalAfterRevision;

final readonly class PendingGroupRow
{
    /**
     * @param  array<int, ?string>  $marks  Activity id => "15,5", "NP" or null for none.
     * @param  string  $termState  scored, incomplete or not_applicable.
     * @param  string  $finalState  scored, incomplete or not_applicable.
     * @param  ?string  $revision  "12,5", "NP" or null for none.
     * @param  string  $standing  studying, approved or failed (RVP-R05.2).
     */
    public function __construct(
        public int $studentId,
        public string $fullName,
        public string $identification,
        public array $marks,
        public string $termState,
        public ?int $termScore,
        public string $finalState,
        public ?int $finalScore,
        public bool $failed,
        public ?string $revision,
        public FinalAfterRevision $decided,
        public string $standing,
    ) {}
}
