<?php

use AulaX\AcademicCalendar\Application\SchoolYearLookup;
use AulaX\AcademicCalendar\Domain\DateRange;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicCalendar\Domain\Term;
use AulaX\AcademicCalendar\Domain\TermNumber;
use AulaX\AcademicCalendar\Domain\TermStatus;
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

it('finds the year just after another one (SPEC-010)', function () {
    $lookup = lookupOver([1 => [2024, SchoolYearStatus::Closed], 2 => [2025, SchoolYearStatus::Active], 4 => [2027, SchoolYearStatus::Planned], 3 => [2026, SchoolYearStatus::Planned]]);

    expect($lookup->nextOf(2))->toBe(3)
        ->and($lookup->nextOf(4))->toBeNull()
        ->and($lookup->nextOf(9))->toBeNull();
});

it('tells when a year starts, for the age at its cutoff (SPEC-010)', function () {
    $lookup = lookupOver([2 => [2026, SchoolYearStatus::Active]]);

    expect($lookup->startOf(2)?->format('Y-m-d'))->toBe('2026-09-16')
        ->and($lookup->startOf(9))->toBeNull();
});

it('tells whether the last term of a year is closed (SPEC-010)', function () {
    $repository = new InMemorySchoolYearRepository;
    $terms = fn (TermStatus $third) => [
        new Term(TermNumber::of(1), DateRange::fromStrings('2026-09-16', '2026-12-15'), TermStatus::Closed),
        new Term(TermNumber::of(2), DateRange::fromStrings('2027-01-07', '2027-03-31'), TermStatus::Closed),
        new Term(TermNumber::of(3), DateRange::fromStrings('2027-04-12', '2027-07-15'), $third),
    ];
    $repository->years[2] = SchoolYear::reconstitute(SchoolYearId::fromInt(2), DateRange::fromStrings('2026-09-16', '2027-07-15'), SchoolYearStatus::Active, $terms(TermStatus::Closed));
    $repository->years[3] = SchoolYear::reconstitute(SchoolYearId::fromInt(3), DateRange::fromStrings('2027-09-16', '2028-07-15'), SchoolYearStatus::Active, $terms(TermStatus::Open));
    $lookup = new SchoolYearLookup($repository);

    expect($lookup->lastTermClosed(2))->toBeTrue()
        ->and($lookup->lastTermClosed(3))->toBeFalse()
        ->and($lookup->lastTermClosed(9))->toBeFalse();
});
