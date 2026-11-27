<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Sheets;

final readonly class SectionEntry
{
    /**
     * @param  string  $kind  literal (Primaria) or report (Inicial).
     */
    public function __construct(
        public int $sectionId,
        public string $section,
        public string $kind,
        public bool $guides,
    ) {}
}
