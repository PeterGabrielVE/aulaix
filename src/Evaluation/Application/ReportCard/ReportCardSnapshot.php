<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\ReportCard;

use AulaX\Evaluation\Application\StudentPendings\StudentPendingView;

/**
 * Public report card snapshot for a student in a school year and section,
 * consumed by SPEC-012 Documents (A-06).
 */
final readonly class ReportCardSnapshot
{
    /**
     * @param  list<SubjectReportRow>  $subjects
     * @param  list<StudentPendingView>  $pendings
     * @param  array<string, array{literal: ?string, description: ?string, incomplete: bool}>  $primaryMarks
     * @param  array<string, array{description: ?string, incomplete: bool}>  $initialReports
     */
    public function __construct(
        public int $studentId,
        public string $level,
        public array $subjects = [],
        public array $pendings = [],
        public array $primaryMarks = [],
        public array $initialReports = [],
    ) {}
}
