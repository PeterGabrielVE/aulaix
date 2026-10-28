<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Sheets;

final readonly class SheetList
{
    /**
     * @param  ?int  $schoolYearId  Null when there is no active year.
     * @param  int  $term  The one to open: the open term, else the last closed, else the first.
     * @param  list<SheetEntry>  $sheets  By section.
     * @param  list<SectionEntry>  $sections  Of Primaria and Inicial, for their literals and reports.
     */
    public function __construct(
        public ?int $schoolYearId,
        public int $term,
        public array $sheets,
        public array $sections = [],
    ) {}
}
