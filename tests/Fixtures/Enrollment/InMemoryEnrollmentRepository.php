<?php

namespace Tests\Fixtures\Enrollment;

use AulaX\Enrollment\Application\Port\EnrollmentList;
use AulaX\Enrollment\Domain\AlreadyEnrolled;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\EnrollmentRepository;

/**
 * The enrollments of one institution, kept in memory, with the unique rule
 * of the database: one current enrollment per student and year.
 */
final class InMemoryEnrollmentRepository implements EnrollmentList, EnrollmentRepository
{
    /** @var array<int, Enrollment> */
    public array $enrollments = [];

    private int $nextId = 1;

    public function byId(EnrollmentId $id): ?Enrollment
    {
        return isset($this->enrollments[$id->value]) ? clone $this->enrollments[$id->value] : null;
    }

    public function currentOf(int $studentId, int $schoolYearId): ?Enrollment
    {
        foreach ($this->enrollments as $enrollment) {
            if ($enrollment->isCurrent() && $enrollment->studentId() === $studentId && $enrollment->schoolYearId() === $schoolYearId) {
                return clone $enrollment;
            }
        }

        return null;
    }

    public function currentOfStudent(int $studentId): array
    {
        return array_values(array_map(fn (Enrollment $enrollment) => clone $enrollment, array_filter(
            $this->enrollments,
            fn (Enrollment $enrollment) => $enrollment->isCurrent() && $enrollment->studentId() === $studentId,
        )));
    }

    public function lastResultOf(int $studentId): ?Enrollment
    {
        $last = null;

        foreach ($this->enrollments as $enrollment) {
            if ($enrollment->studentId() === $studentId && $enrollment->result() !== null
                && ($last === null || $enrollment->period()->startsOn >= $last->period()->startsOn)) {
                $last = $enrollment;
            }
        }

        return $last === null ? null : clone $last;
    }

    public function currentCountIn(int $sectionId): int
    {
        return count(array_filter(
            $this->enrollments,
            fn (Enrollment $enrollment) => $enrollment->isCurrent() && $enrollment->placement()->sectionId === $sectionId,
        ));
    }

    public function hasAnyIn(int $sectionId): bool
    {
        foreach ($this->enrollments as $enrollment) {
            if ($enrollment->placement()->sectionId === $sectionId) {
                return true;
            }
        }

        return false;
    }

    public function save(Enrollment $enrollment): EnrollmentId
    {
        foreach ($this->enrollments as $id => $other) {
            if ($id !== $enrollment->id()?->value && $enrollment->isCurrent() && $other->isCurrent()
                && $other->studentId() === $enrollment->studentId() && $other->schoolYearId() === $enrollment->schoolYearId()) {
                throw AlreadyEnrolled::in(null, null);
            }
        }

        $id = $enrollment->id() ?? EnrollmentId::fromInt($this->nextId++);
        $enrollment->identify($id);
        $this->enrollments[$id->value] = clone $enrollment;

        return $id;
    }

    /**
     * @return list<Enrollment>
     */
    public function current(): array
    {
        return array_values(array_filter($this->enrollments, fn (Enrollment $enrollment) => $enrollment->isCurrent()));
    }

    public function currentCountsOf(int $schoolYearId): array
    {
        $counts = [];

        foreach ($this->current() as $enrollment) {
            if ($enrollment->schoolYearId() === $schoolYearId) {
                $counts[$enrollment->placement()->sectionId] = ($counts[$enrollment->placement()->sectionId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function currentCountsByGrade(int $schoolYearId): array
    {
        $counts = [];

        foreach ($this->currentInYear($schoolYearId) as $enrollment) {
            $counts[$enrollment->placement()->grade] = ($counts[$enrollment->placement()->grade] ?? 0) + 1;
        }

        return $counts;
    }

    public function currentIn(int $sectionId): array
    {
        return array_values(array_filter($this->current(), fn (Enrollment $enrollment) => $enrollment->placement()->sectionId === $sectionId));
    }

    public function currentStudentsOf(int $schoolYearId): array
    {
        return array_values(array_map(
            fn (Enrollment $enrollment) => $enrollment->studentId(),
            array_filter($this->current(), fn (Enrollment $enrollment) => $enrollment->schoolYearId() === $schoolYearId),
        ));
    }

    public function currentStudentsIn(int $schoolYearId, array $sectionIds, array $grades): array
    {
        return array_values(array_map(fn (Enrollment $enrollment) => $enrollment->studentId(), array_filter(
            $this->currentInYear($schoolYearId),
            fn (Enrollment $enrollment) => in_array($enrollment->placement()->sectionId, $sectionIds, true) || in_array($enrollment->placement()->grade, $grades, true),
        )));
    }

    public function currentInYear(int $schoolYearId): array
    {
        return array_values(array_filter($this->current(), fn (Enrollment $enrollment) => $enrollment->schoolYearId() === $schoolYearId));
    }

    public function currentWithoutResult(int $schoolYearId): array
    {
        return array_values(array_filter(
            $this->current(),
            fn (Enrollment $enrollment) => $enrollment->schoolYearId() === $schoolYearId && $enrollment->result() === null,
        ));
    }

    public function hasAnyInYear(int $schoolYearId): bool
    {
        foreach ($this->enrollments as $enrollment) {
            if ($enrollment->schoolYearId() === $schoolYearId) {
                return true;
            }
        }

        return false;
    }

    public function ofStudentsInYear(int $schoolYearId, array $studentIds): array
    {
        return array_values(array_filter(
            $this->enrollments,
            fn (Enrollment $enrollment) => $enrollment->schoolYearId() === $schoolYearId && in_array($enrollment->studentId(), $studentIds, true),
        ));
    }

    public function historyOf(int $studentId): array
    {
        return array_values(array_filter($this->enrollments, fn (Enrollment $enrollment) => $enrollment->studentId() === $studentId));
    }

    public function withPendingSubjects(): array
    {
        return array_values(array_filter($this->enrollments, fn (Enrollment $enrollment) => $enrollment->result()?->outcome->value === 'promoted_with_pending'));
    }
}
