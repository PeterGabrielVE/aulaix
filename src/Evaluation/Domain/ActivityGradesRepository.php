<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

use DateTimeImmutable;

/**
 * The grades of the activities of the open institution (Row Level
 * Security, S-01).
 */
interface ActivityGradesRepository
{
    /**
     * @param  int|PlanOwner  $owner  The section of its plan by its id, or any owner.
     */
    public function forActivity(Activity $activity, int|PlanOwner $owner): ActivityGrades;

    public function saveGrades(ActivityGrades $grades): void;

    /**
     * Which of these activities have any grade or NP.
     *
     * @param  list<int>  $activityIds
     * @return list<int>
     */
    public function withGrades(array $activityIds): array;

    /**
     * Whether the student has a grade or NP in an activity of the section
     * dated within the period (the end excluded).
     */
    public function gradedWithin(int $studentId, int $sectionId, DateTimeImmutable $from, ?DateTimeImmutable $until): bool;

    /**
     * Which of these subjects have any grade or NP in these sections this
     * year.
     *
     * @param  list<int>  $sectionIds
     * @param  list<int>  $subjectIds
     * @return list<int>
     */
    public function subjectsWithGrades(int $schoolYearId, array $sectionIds, array $subjectIds): array;
}
