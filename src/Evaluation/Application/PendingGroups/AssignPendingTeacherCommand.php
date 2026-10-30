<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

final readonly class AssignPendingTeacherCommand
{
    /**
     * @param  ?int  $accountId  Null leaves the group without a teacher.
     */
    public function __construct(
        public int $schoolYearId,
        public int $subjectId,
        public string $originGrade,
        public ?int $accountId,
    ) {}
}
