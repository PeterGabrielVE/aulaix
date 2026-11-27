<?php

namespace Tests\Fixtures\AcademicCalendar;

use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;

/**
 * The school years of one institution, kept in memory.
 */
final class InMemorySchoolYearRepository implements SchoolYearRepository
{
    /** @var array<int, SchoolYear> */
    public array $years = [];

    private int $nextId = 1;

    public function byId(SchoolYearId $id): ?SchoolYear
    {
        return $this->years[$id->value] ?? null;
    }

    public function othersOf(?SchoolYearId $except): array
    {
        return array_values(array_filter($this->years, fn (SchoolYear $year) => $except === null || ! $year->id()->equals($except)));
    }

    public function save(SchoolYear $year): SchoolYearId
    {
        $year->identify(SchoolYearId::fromInt($this->nextId++));
        $this->years[$year->id()->value] = $year;

        return $year->id();
    }

    public function delete(SchoolYearId $id): void
    {
        unset($this->years[$id->value]);
    }
}
