<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Marks;

use AulaX\Evaluation\Application\Port\SectionInfo;

final readonly class MarksSheetView
{
    /**
     * @param  string  $kind  literal (Primaria) or report (Inicial).
     * @param  bool  $canGrade  The person guides the section.
     * @param  array<int, bool>  $editable  Term => whether the person may change it now (EVA-R06).
     * @param  bool  $finalOpen  The person may record the final literal (EVA-R04.4).
     * @param  list<MarksRow>  $rows  By name.
     */
    public function __construct(
        public int $schoolYearId,
        public SectionInfo $section,
        public string $kind,
        public bool $canGrade,
        public array $editable,
        public bool $finalOpen,
        public array $rows,
    ) {}
}
