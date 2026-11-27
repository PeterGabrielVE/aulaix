<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Marks;

final readonly class MarksRow
{
    /**
     * @param  array<string, ?array{literal: ?string, description: ?string, incomplete: bool}>  $marks  Period ('1', '2', '3', 'final') => mark, or null for none.
     */
    public function __construct(
        public int $studentId,
        public string $fullName,
        public string $identification,
        public array $marks,
    ) {}
}
