<?php

use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\TermNumber;

/**
 * SPEC-007 CAL-R05.1 – R05.3: which terms admit grade changes.
 */
function runningYear(): SchoolYear
{
    $year = SchoolYear::plan(SchoolYearDates::fromStrings('2026-09-16', '2027-07-15', [
        ['2026-09-16', '2026-12-15'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15'],
    ]));
    $year->activate();
    $year->closeTerm(confirmed: true);

    return $year;
}

it('lets anyone change the grades of the open term', function () {
    expect(runningYear()->allowsGradeChanges(TermNumber::of(2), hasClosedTermPermission: false))->toBeTrue();
});

it('locks closed and not yet open terms, except for the special permission', function (int $term) {
    expect(runningYear()->allowsGradeChanges(TermNumber::of($term), hasClosedTermPermission: false))->toBeFalse()
        ->and(runningYear()->allowsGradeChanges(TermNumber::of($term), hasClosedTermPermission: true))->toBeTrue();
})->with(['closed' => [1], 'not yet open' => [3]]);

it('locks every term of a closed year, even with the special permission', function () {
    $year = runningYear();
    $year->closeTerm(confirmed: true);
    $year->closeTerm(confirmed: true);
    $year->close(confirmed: true);

    foreach (TermNumber::all() as $term) {
        expect($year->allowsGradeChanges($term, hasClosedTermPermission: true))->toBeFalse();
    }
});
