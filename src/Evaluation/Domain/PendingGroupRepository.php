<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The groups of pending subjects of the open institution (Row Level
 * Security, S-01).
 */
interface PendingGroupRepository
{
    public function groupById(int $id): ?PendingGroup;

    public function groupOf(int $schoolYearId, int $subjectId, string $originGrade): ?PendingGroup;

    /**
     * The groups of a subject of a grade of origin, of every year.
     *
     * @return list<PendingGroup>
     */
    public function groupsOfSubject(int $subjectId, string $originGrade): array;

    /**
     * @return list<PendingGroup>
     */
    public function groupsOf(int $schoolYearId): array;

    public function saveGroup(PendingGroup $group): void;
}
