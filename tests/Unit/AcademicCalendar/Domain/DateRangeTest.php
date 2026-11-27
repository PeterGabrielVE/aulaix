<?php

use AulaX\AcademicCalendar\Domain\DateRange;
use AulaX\AcademicCalendar\Domain\TermNumber;

/**
 * SPEC-007: the date ranges of school years and terms. Whole days, both ends
 * included.
 */
it('keeps whole days, ignoring any time of day', function () {
    $range = DateRange::fromStrings('2026-09-16', '2027-07-15');

    expect($range->start->format('Y-m-d H:i'))->toBe('2026-09-16 00:00')
        ->and($range->end->format('Y-m-d'))->toBe('2027-07-15');
});

it('refuses a range that ends before it starts', function () {
    expect(fn () => DateRange::fromStrings('2026-09-16', '2026-09-15'))->toThrow(InvalidArgumentException::class);
});

it('accepts a single day', function () {
    expect(DateRange::fromStrings('2026-09-16', '2026-09-16')->start)->toEqual(DateRange::fromStrings('2026-09-16', '2026-09-16')->end);
});

it('tells whether it contains another range, ends included', function () {
    $year = DateRange::fromStrings('2026-09-16', '2027-07-15');

    expect($year->contains(DateRange::fromStrings('2026-09-16', '2026-12-15')))->toBeTrue()
        ->and($year->contains(DateRange::fromStrings('2027-05-01', '2027-07-15')))->toBeTrue()
        ->and($year->contains(DateRange::fromStrings('2026-09-15', '2026-12-15')))->toBeFalse()
        ->and($year->contains(DateRange::fromStrings('2027-05-01', '2027-07-16')))->toBeFalse();
});

it('tells whether two ranges overlap, sharing a single day included', function () {
    $first = DateRange::fromStrings('2026-09-16', '2026-12-15');

    expect($first->overlaps(DateRange::fromStrings('2026-12-15', '2027-03-15')))->toBeTrue()
        ->and($first->overlaps(DateRange::fromStrings('2026-12-16', '2027-03-15')))->toBeFalse()
        ->and($first->overlaps(DateRange::fromStrings('2026-01-01', '2026-09-16')))->toBeTrue();
});

it('tells whether it starts after another one ends', function () {
    $first = DateRange::fromStrings('2026-09-16', '2026-12-15');

    expect(DateRange::fromStrings('2026-12-16', '2027-03-15')->startsAfter($first))->toBeTrue()
        ->and(DateRange::fromStrings('2026-12-15', '2027-03-15')->startsAfter($first))->toBeFalse();
});

it('numbers terms from 1 to 3', function () {
    expect(TermNumber::of(1)->value)->toBe(1)
        ->and(TermNumber::of(3)->isLast())->toBeTrue()
        ->and(TermNumber::of(2)->next()?->value)->toBe(3)
        ->and(TermNumber::of(3)->next())->toBeNull()
        ->and(fn () => TermNumber::of(4))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TermNumber::of(0))->toThrow(InvalidArgumentException::class);
});
