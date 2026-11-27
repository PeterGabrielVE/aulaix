<?php

use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\SchoolYearSchedule;
use AulaX\AcademicCalendar\Domain\TermNumber;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-007 CAL-R01.6, R01.9, R02.1, R02.3: the rules across the years of one
 * institution.
 */
function yearStarting(int $year, string $state = 'planned'): SchoolYear
{
    $next = $year + 1;
    $schoolYear = SchoolYear::plan(SchoolYearDates::fromStrings("{$year}-09-16", "{$next}-07-15", [
        ["{$year}-09-16", "{$year}-12-15"], ["{$next}-01-07", "{$next}-03-31"], ["{$next}-04-12", "{$next}-07-15"],
    ]));

    if ($state !== 'planned') {
        $schoolYear->activate();
    }

    if ($state === 'closed') {
        foreach (TermNumber::all() as $term) {
            $schoolYear->closeTerm(confirmed: true);
        }
        $schoolYear->close(confirmed: true);
    }

    return $schoolYear;
}

function scheduleMessage(Closure $check): ?string
{
    try {
        $check();
    } catch (BusinessRuleViolation $violation) {
        return $violation->getMessage();
    }

    return null;
}

it('allows planning the next year while the current one is active', function () {
    expect(scheduleMessage(fn () => (new SchoolYearSchedule)->assertCanPlan(yearStarting(2027), [yearStarting(2026, 'active')])))->toBeNull();
});

it('allows a single planned year', function () {
    expect(scheduleMessage(fn () => (new SchoolYearSchedule)->assertCanPlan(yearStarting(2028), [yearStarting(2027)])))
        ->toBe('Ya hay un año planificado en este plantel.');
});

it('refuses dates that overlap another year, whatever its state', function (string $state) {
    $overlapping = SchoolYear::plan(SchoolYearDates::fromStrings('2027-07-15', '2028-06-10', [
        ['2027-09-16', '2027-12-15'], ['2028-01-07', '2028-03-31'], ['2028-04-12', '2028-06-10'],
    ]));

    expect(scheduleMessage(fn () => (new SchoolYearSchedule)->assertDatesFit($overlapping, [yearStarting(2026, $state)])))
        ->toBe('Las fechas se solapan con el año 2026-2027.');
})->with(['planned', 'active', 'closed']);

it('allows a single active year', function () {
    expect(scheduleMessage(fn () => (new SchoolYearSchedule)->assertCanActivate([yearStarting(2026, 'active')])))
        ->toBe('Ya hay un año activo: ciérralo antes de activar otro.')
        ->and(scheduleMessage(fn () => (new SchoolYearSchedule)->assertCanActivate([yearStarting(2026, 'closed')])))->toBeNull();
});
