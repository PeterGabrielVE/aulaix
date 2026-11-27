<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\ReportCard;

/**
 * A single subject row on a Media General report card (SPEC-012 DOC-R01.1,
 * R02.1; SPEC-011 EVA-R03; SPEC-016 RVP-R02).
 */
final readonly class SubjectReportRow
{
    public function __construct(
        public int $subjectId,
        public string $subjectName,
        public ?string $term1,
        public ?string $term2,
        public ?string $term3,
        public ?string $termScore,
        public ?string $finalScore,
        public ?string $revision,
        public ?string $finalAfterRevision,
        public bool $failed,
    ) {}
}
