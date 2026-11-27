<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Gradebook;

use AulaX\Evaluation\Application\Port\SectionInfo;

final readonly class GradebookView
{
    /**
     * @param  bool  $canGrade  The person teaches the subject in the section.
     * @param  bool  $editable  The term takes grades now (EVA-R06).
     * @param  list<ActivityView>  $activities
     * @param  list<GradebookRow>  $rows  By name.
     * @param  bool  $revisionsOpen  The person records the revisions now (RVP-R01.5, R01.6).
     */
    public function __construct(
        public int $schoolYearId,
        public SectionInfo $section,
        public int $subjectId,
        public string $subject,
        public int $term,
        public bool $canGrade,
        public bool $editable,
        public array $activities,
        public array $rows,
        public bool $revisionsOpen = false,
    ) {}
}
