<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Application\StudentPendings;

use AulaX\Evaluation\Application\PendingGroups\PendingMembers;
use AulaX\Evaluation\Application\Port\CalendarFacts;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\TeachingFacts;
use AulaX\Evaluation\Domain\OwedPending;
use AulaX\Evaluation\Domain\PendingStanding;
use Collator;

/**
 * The pending subjects of a student, for its record (RVP-R05.2, R06.1):
 * the grade and year where each was failed, and how it stands. Approved in
 * the group of any later year, with that year and grade; failed in the
 * group of the active year, with its final grade; otherwise studied.
 * Whoever asks already checked who may see the record (RVP-R06.2).
 */
final readonly class StudentPendingsQuery
{
    public function __construct(
        private PendingMembers $members,
        private EnrollmentFacts $enrollments,
        private TeachingFacts $teaching,
        private CalendarFacts $calendar,
    ) {}

    /**
     * @return list<StudentPendingView> By the year it was failed in, then by subject.
     */
    public function __invoke(int $studentId): array
    {
        $owed = $this->enrollments->pendingsOf($studentId);

        if ($owed === []) {
            return [];
        }

        $subjects = $this->teaching->subjectNames('media_general');
        $grades = $this->teaching->gradeNames();
        $years = $this->calendar->yearNames();
        $activeYear = $this->calendar->activeYear();
        $collator = new Collator('es');
        usort($owed, fn (OwedPending $a, OwedPending $b) => $a->originSchoolYearId <=> $b->originSchoolYearId
            ?: $collator->compare($subjects[$a->subjectId] ?? '', $subjects[$b->subjectId] ?? ''));
        $views = [];

        foreach ($owed as $pending) {
            [$standing, $score, $decidedIn] = $this->standingOf($pending, $activeYear);
            $views[] = new StudentPendingView(
                $pending->subjectId,
                $subjects[$pending->subjectId] ?? '',
                $grades[$pending->originGrade] ?? $pending->originGrade,
                $years[$pending->originSchoolYearId] ?? '',
                $standing,
                $score,
                $decidedIn === null ? null : ($years[$decidedIn] ?? ''),
            );
        }

        return $views;
    }

    /**
     * @return array{0: string, 1: ?int, 2: ?int} The standing, the final grade after revision and the year it was decided in.
     */
    private function standingOf(OwedPending $pending, ?int $activeYear): array
    {
        $current = null;

        foreach ($this->members->groupsOf($pending) as $group) {
            $scores = $this->members->scoresOf($group);
            $standing = $scores->standing($pending->studentId);

            if ($standing === PendingStanding::APPROVED) {
                return [$standing, $scores->result($pending->studentId)->score, $group->schoolYearId()];
            }

            if ($group->schoolYearId() === $activeYear && $standing === PendingStanding::FAILED) {
                $current = [$standing, $scores->result($pending->studentId)->score, $group->schoolYearId()];
            }
        }

        return $current ?? [PendingStanding::STUDYING, null, null];
    }
}
