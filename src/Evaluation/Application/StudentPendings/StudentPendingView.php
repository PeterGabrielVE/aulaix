<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\StudentPendings;

final readonly class StudentPendingView
{
    /**
     * @param  string  $originGrade  The name of the grade where it was failed.
     * @param  string  $originSchoolYear  The name of the year it was failed in.
     * @param  string  $standing  studying, approved or failed (PendingStanding).
     * @param  ?int  $score  The final grade after revision, once decided.
     * @param  ?string  $decidedIn  The name of the year it was approved or failed in.
     */
    public function __construct(
        public int $subjectId,
        public string $subject,
        public string $originGrade,
        public string $originSchoolYear,
        public string $standing,
        public ?int $score,
        public ?string $decidedIn,
    ) {}
}
