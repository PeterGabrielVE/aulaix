<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Port;

final readonly class SectionInfo
{
    /**
     * @param  string  $level  inicial, primaria or media_general.
     */
    public function __construct(
        public int $id,
        public int $schoolYearId,
        public string $grade,
        public string $gradeName,
        public string $level,
        public string $letter,
    ) {}
}
