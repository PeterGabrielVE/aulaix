<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Gradebook;

final readonly class GradebookRow
{
    /**
     * @param  array<int, ?string>  $marks  Activity id => "15,5", "NP", "NA" or null for none.
     * @param  string  $termState  scored, incomplete or not_applicable.
     * @param  string  $finalState  scored, incomplete or not_applicable.
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
    ) {}
}
