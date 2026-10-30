<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The revisions of the open institution (Row Level Security, S-01).
 */
interface RevisionRepository
{
    /**
     * The revisions of these students in a subject and year, of a section or
     * of a group of pending subjects; an empty one for whoever has none.
     *
     * @param  list<int>  $studentIds
     * @return array<int, Revision> Student id => revision.
     */
    public function revisionsOf(int $schoolYearId, int $subjectId, PlanOwner $owner, array $studentIds): array;

    public function saveRevision(Revision $revision): void;
}
