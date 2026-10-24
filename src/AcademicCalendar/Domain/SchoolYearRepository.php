<?php

declare(strict_types=1);

namespace AulaX\AcademicCalendar\Domain;

/**
 * The school years of the active institution (Row Level Security limits
 * every read and write to it), each with its three terms.
 */
interface SchoolYearRepository
{
    public function byId(SchoolYearId $id): ?SchoolYear;

    /**
     * Every year of the institution but the given one (all of them when
     * null), for SchoolYearSchedule.
     *
     * @return list<SchoolYear>
     */
    public function othersOf(?SchoolYearId $except): array;

    /**
     * Persists the year and its terms; returns its id.
     */
    public function save(SchoolYear $year): SchoolYearId;

    /**
     * With its terms.
     */
    public function delete(SchoolYearId $id): void;
}
