<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

use AulaX\Evaluation\Application\Gradebook\ActivityView;

final readonly class PendingGroupSheetView
{
    /**
     * @param  bool  $canGrade  The person is the teacher assigned to the group.
     * @param  bool  $editable  The term takes grades now (EVA-R06).
     * @param  bool  $revisionsOpen  The person records the revisions now (RVP-R05.1).
     * @param  list<ActivityView>  $activities
     * @param  list<PendingGroupRow>  $rows  By name.
     */
    public function __construct(
        public int $groupId,
        public int $schoolYearId,
        public int $subjectId,
        public string $subject,
        public string $originGrade,
        public int $term,
        public bool $canGrade,
        public bool $editable,
        public bool $revisionsOpen,
        public array $activities,
        public array $rows,
        public string $originGradeName = '',
    ) {}
}
