<?php

use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;

/*
 * Helpers shared by the tests that need school years in the database.
 */

/**
 * A school year of the open institution, saved: planned, active (with some
 * terms closed) or closed. Returns its id.
 */
function storedSchoolYear(int $start, string $state = 'planned', int $closedTerms = 0): int
{
    $end = $start + 1;
    $year = SchoolYear::plan(SchoolYearDates::fromStrings("{$start}-09-16", "{$end}-07-15", [
        ["{$start}-09-16", "{$start}-12-15"], ["{$end}-01-07", "{$end}-03-31"], ["{$end}-04-12", "{$end}-07-15"],
    ]));

    if ($state !== 'planned') {
        $year->activate();

        for ($closed = 0; $closed < $closedTerms; $closed++) {
            $year->closeTerm(confirmed: true);
        }
    }

    if ($state === 'closed') {
        $year->close(confirmed: true);
    }

    return app(SchoolYearRepository::class)->save($year)->value;
}

function activateStoredSchoolYear(int $id): void
{
    $repository = app(SchoolYearRepository::class);
    $year = $repository->byId(SchoolYearId::fromInt($id));
    $year->activate();
    $repository->save($year);
}
