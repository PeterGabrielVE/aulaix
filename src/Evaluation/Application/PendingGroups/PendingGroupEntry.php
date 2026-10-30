<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\PendingGroups;

final readonly class PendingGroupEntry
{
    /**
     * @param  ?int  $groupId  Null until a teacher is assigned to it.
     * @param  list<array{studentId: int, fullName: string, identification: string}>  $members  By name.
     */
    public function __construct(
        public ?int $groupId,
        public int $subjectId,
        public string $subject,
        public string $originGrade,
        public ?int $teacherAccountId,
        public ?string $teacher,
        public array $members,
        public string $originGradeName = '',
    ) {}
}
