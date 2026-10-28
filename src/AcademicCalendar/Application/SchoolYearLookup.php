<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application;

use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicCalendar\Domain\TermStatus;
use DateTimeImmutable;

/**
 * The public API of the calendar for other modules (SPEC-008): the state of
 * a school year of the open institution, its active year and the year
 * before a given one. Ids and plain strings, never the aggregate.
 */
final readonly class SchoolYearLookup
{
    public function __construct(private SchoolYearRepository $years) {}

    /**
     * @return ?string planned, active or closed; null for a year the institution does not have.
     */
    public function statusOf(int $schoolYearId): ?string
    {
        if ($schoolYearId <= 0) {
            return null;
        }

        return $this->years->byId(SchoolYearId::fromInt($schoolYearId))?->status()->value;
    }

    public function activeYear(): ?int
    {
        foreach ($this->years->othersOf(null) as $year) {
            if ($year->status() === SchoolYearStatus::Active) {
                return $year->id()?->value;
            }
        }

        return null;
    }

    /**
     * The first day of the year (SPEC-010: the age of a student at its
     * cutoff).
     */
    public function startOf(int $schoolYearId): ?DateTimeImmutable
    {
        return $schoolYearId > 0 ? $this->years->byId(SchoolYearId::fromInt($schoolYearId))?->dates()->start : null;
    }

    /**
     * Whether the year's third term is closed (SPEC-010: promotion results
     * are recorded from then on).
     */
    public function lastTermClosed(int $schoolYearId): bool
    {
        $year = $schoolYearId > 0 ? $this->years->byId(SchoolYearId::fromInt($schoolYearId)) : null;

        foreach ($year?->terms() ?? [] as $term) {
            if ($term->number->isLast()) {
                return $term->status === TermStatus::Closed;
            }
        }

        return false;
    }

    /**
     * The earliest year that starts after this one ends (SPEC-010: where its
     * students go on).
     */
    public function nextOf(int $schoolYearId): ?int
    {
        $year = $schoolYearId > 0 ? $this->years->byId(SchoolYearId::fromInt($schoolYearId)) : null;

        if ($year === null) {
            return null;
        }

        $later = array_filter(
            $this->years->othersOf($year->id()),
            fn (SchoolYear $other) => $other->dates()->start > $year->dates()->end,
        );
        usort($later, fn (SchoolYear $a, SchoolYear $b) => $a->dates()->start <=> $b->dates()->start);

        return ($later[0] ?? null)?->id()?->value;
    }

    /**
     * The latest year that ends before this one starts.
     */
    public function previousOf(int $schoolYearId): ?int
    {
        $year = $schoolYearId > 0 ? $this->years->byId(SchoolYearId::fromInt($schoolYearId)) : null;

        if ($year === null) {
            return null;
        }

        $earlier = array_filter(
            $this->years->othersOf($year->id()),
            fn (SchoolYear $other) => $other->dates()->end < $year->dates()->start,
        );
        usort($earlier, fn (SchoolYear $a, SchoolYear $b) => $b->dates()->start <=> $a->dates()->start);

        return ($earlier[0] ?? null)?->id()?->value;
    }
}
