<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\Pending;

use AulaX\Evaluation\Domain\Activity;

/**
 * What a section still lacks in a term: the grades of an activity, a plan
 * for a subject, or the literals or reports of its students.
 */
final readonly class PendingItem
{
    public const string ACTIVITY = 'activity';

    public const string NO_PLAN = 'no_plan';

    public const string LITERAL = 'literal';

    public const string REPORT = 'report';

    /**
     * @param  string  $kind  activity, no_plan, literal or report.
     * @param  list<int>  $students  Ids of the students it lacks.
     * @param  ?int  $pendingGroupId  The group of pending subjects it is of (SPEC-016), when it is; its sectionId is then 0.
     * @param  ?int  $teacherAccountId  The teacher of that group.
     */
    public function __construct(
        public string $kind,
        public int $sectionId,
        public string $section,
        public ?int $subjectId,
        public ?string $subject,
        public ?Activity $activity,
        public array $students,
        public bool $ofPendingGroup = false,
        public ?int $pendingGroupId = null,
        public ?int $teacherAccountId = null,
    ) {}
}
