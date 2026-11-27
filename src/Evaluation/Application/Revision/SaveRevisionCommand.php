<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Revision;

final readonly class SaveRevisionCommand
{
    /**
     * @param  array<int, string|int|float|null>  $revisions  Student id => "12,5", "NP" or null for none.
     */
    public function __construct(
        public int $schoolYearId,
        public int $sectionId,
        public int $subjectId,
        public array $revisions,
    ) {}
}
