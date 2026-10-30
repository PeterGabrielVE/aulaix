<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure;

use AulaX\Enrollment\Application\Roster\EnrollmentPeriodsQuery;
use AulaX\Enrollment\Application\Roster\PendingSubjectsQuery;
use AulaX\Enrollment\Application\Roster\PendingSubjectView;
use AulaX\Enrollment\Application\Roster\PeriodView;
use AulaX\Enrollment\Application\Roster\RosterStudent;
use AulaX\Enrollment\Application\Roster\SectionRosterQuery;
use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\RosterStudentFact;
use AulaX\Evaluation\Domain\EnrollmentPeriod;
use AulaX\Evaluation\Domain\OwedPending;
use AulaX\Students\Application\Enrollable\EnrollableStudent;
use AulaX\Students\Application\Enrollable\EnrollableStudentsQuery;
use DateTimeImmutable;

/**
 * The students of a section and their enrollment periods, through
 * Enrollment's public queries (A-06, MAT-R09.3).
 */
final readonly class EnrollmentEnrollmentFacts implements EnrollmentFacts
{
    public function __construct(
        private SectionRosterQuery $roster,
        private EnrollmentPeriodsQuery $periods,
        private PendingSubjectsQuery $pendings,
        private EnrollableStudentsQuery $enrollable,
    ) {}

    public function roster(int $schoolYearId, int $sectionId): array
    {
        return array_map(
            fn (RosterStudent $student) => new RosterStudentFact($student->studentId, $student->fullName, $student->identification),
            ($this->roster)($schoolYearId, $sectionId)->students,
        );
    }

    public function periods(int $schoolYearId, array $studentIds): array
    {
        return array_map(fn (array $periods) => array_map(self::period(...), $periods), ($this->periods)($schoolYearId, $studentIds));
    }

    public function enrollment(int $enrollmentId): ?array
    {
        $enrolled = $this->periods->ofEnrollment($enrollmentId);

        return $enrolled === null ? null : ['studentId' => $enrolled->studentId, 'schoolYearId' => $enrolled->schoolYearId, 'period' => self::period($enrolled->period)];
    }

    public function resultsRecorded(int $schoolYearId, array $studentIds): array
    {
        return $this->periods->withResult($schoolYearId, $studentIds);
    }

    public function owedPendings(int $schoolYearId): array
    {
        return array_map(
            fn (PendingSubjectView $view) => new OwedPending($view->studentId, $view->subjectId, $view->originGrade, $view->originSchoolYearId),
            ($this->pendings)($schoolYearId),
        );
    }

    private static function period(PeriodView $period): EnrollmentPeriod
    {
        return new EnrollmentPeriod(
            $period->sectionId,
            $period->grade,
            new DateTimeImmutable($period->startsOn),
            $period->endsOn === null ? null : new DateTimeImmutable($period->endsOn),
        );
    }

    public function students(array $studentIds): array
    {
        return array_map(
            fn (EnrollableStudent $student) => new RosterStudentFact($student->id, $student->fullName, $student->identification),
            $this->enrollable->byIds($studentIds),
        );
    }
}
