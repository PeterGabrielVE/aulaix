<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * A grade that changed, as shown: "15,5", "NP" or null for none.
 */
final readonly class GradeChange
{
    public function __construct(
        public int $studentId,
        public ?string $before,
        public ?string $after,
    ) {}

    public static function display(?GradeMark $mark): ?string
    {
        return match (true) {
            $mark === null => null,
            $mark->isNotPresented() => 'NP',
            default => $mark->scoreOrNull()?->display(),
        };
    }
}
