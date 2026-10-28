<?php

namespace Tests\Fixtures\Evaluation;

use AulaX\Evaluation\Application\Port\EnrollmentFacts;
use AulaX\Evaluation\Application\Port\RosterStudentFact;
use AulaX\Evaluation\Domain\EnrollmentPeriod;
use DateTimeImmutable;

/**
 * Who is in each section and since when, as a test sets it up.
 */
final class FakeEnrollmentFacts implements EnrollmentFacts
{
    /** @var array<int, list<EnrollmentPeriod>> Student => periods. */
    public array $periods = [];

    /** @var array<int, string> Student => name. */
    public array $names = [];

    public function enroll(int $studentId, int $sectionId, string $grade = 'media_3', string $from = '2026-09-16', ?string $until = null, ?string $name = null): void
    {
        $this->periods[$studentId][] = new EnrollmentPeriod($sectionId, $grade, new DateTimeImmutable($from), $until === null ? null : new DateTimeImmutable($until));
        $this->names[$studentId] = $name ?? "Estudiante {$studentId}";
    }

    public function roster(int $schoolYearId, int $sectionId): array
    {
        $roster = [];

        foreach ($this->periods as $studentId => $periods) {
            foreach ($periods as $period) {
                if ($period->sectionId === $sectionId && $period->endsOn === null) {
                    $roster[] = new RosterStudentFact($studentId, $this->names[$studentId], "V3000000{$studentId}");
                }
            }
        }

        usort($roster, fn (RosterStudentFact $a, RosterStudentFact $b) => strcmp($a->fullName, $b->fullName));

        return $roster;
    }

    public function periods(int $schoolYearId, array $studentIds): array
    {
        return array_intersect_key($this->periods, array_flip($studentIds));
    }
}
