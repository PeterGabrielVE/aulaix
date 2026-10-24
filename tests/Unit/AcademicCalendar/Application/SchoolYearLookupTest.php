<?php

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicCalendar\Domain\DateRange;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use Tests\Fixtures\AcademicCalendar\InMemorySchoolYearRepository;

/**
 * SPEC-008 (public API of the calendar): the state of a school year, the
 * active one and the one before a given year.
 */
function lookupOver(array $years): SchoolYearLookup
{
    $repository = new InMemorySchoolYearRepository;

    foreach ($years as $id => [$start, $status]) {
        $repository->years[$id] = SchoolYear::reconstitute(
            SchoolYearId::fromInt($id),
            DateRange::fromStrings("{$start}-09-16", ($start + 1).'-07-15'),
            $status,
            [],
        );
    }

    return new SchoolYearLookup($repository);
}

it('tells the state of a year, or nothing for one it does not have', function () {
    $lookup = lookupOver([1 => [2025, SchoolYearStatus::Closed], 2 => [2026, SchoolYearStatus::Active]]);

    expect($lookup->statusOf(1))->toBe('closed')
        ->and($lookup->statusOf(2))->toBe('active')
        ->and($lookup->statusOf(9))->toBeNull()
        ->and($lookup->statusOf(0))->toBeNull();
});

it('finds the active year', function () {
    expect(lookupOver([1 => [2025, SchoolYearStatus::Closed], 2 => [2026, SchoolYearStatus::Active]])->activeYear())->toBe(2)
        ->and(lookupOver([1 => [2025, SchoolYearStatus::Closed]])->activeYear())->toBeNull();
});

it('finds the year just before another one', function () {
    $lookup = lookupOver([1 => [2024, SchoolYearStatus::Closed], 2 => [2025, SchoolYearStatus::Closed], 3 => [2026, SchoolYearStatus::Planned]]);

    expect($lookup->previousOf(3))->toBe(2)
        ->and($lookup->previousOf(1))->toBeNull();
});
