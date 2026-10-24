<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Application;

use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;

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
