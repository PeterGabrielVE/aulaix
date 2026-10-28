<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Domain;

/**
 * The grades of the activities of the open institution (Row Level
 * Security, S-01).
 */
interface ActivityGradesRepository
{
    public function forActivity(Activity $activity, int $sectionId): ActivityGrades;

    public function saveGrades(ActivityGrades $grades): void;

    /**
     * Which of these activities have any grade or NP.
     *
     * @param  list<int>  $activityIds
     * @return list<int>
     */
    public function withGrades(array $activityIds): array;
}
