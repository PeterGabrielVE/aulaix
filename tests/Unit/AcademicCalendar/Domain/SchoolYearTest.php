<?php

use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicCalendar\Domain\TermNumber;
use AulaX\AcademicCalendar\Domain\TermStatus;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;

/**
 * SPEC-007 CAL-R01 – R04, R09: a school year and its three terms.
 */
function yearDates(string $start = '2026-09-16', string $end = '2027-07-15', array $terms = [
    ['2026-09-16', '2026-12-15'],
    ['2027-01-07', '2027-03-31'],
    ['2027-04-12', '2027-07-15'],
]): SchoolYearDates
{
    return SchoolYearDates::fromStrings($start, $end, $terms);
}

/**
 * The messages of a rejected set of dates, by field.
 */
function calendarViolations(Closure $operation): array
{
    try {
        $operation();
    } catch (BusinessRuleViolations $violations) {
        return $violations->messagesByField();
    } catch (BusinessRuleViolation $violation) {
        return [$violation->field() => [$violation->getMessage()]];
    }

    return [];
}

function activeYear(): SchoolYear
{
    $year = SchoolYear::plan(yearDates());
    $year->activate();

    return $year;
}

function termStatuses(SchoolYear $year): array
{
    return array_map(fn ($term) => $term->status, $year->terms());
}

// CAL-R01 — planning

it('is planned with its three terms not yet open', function () {
    $year = SchoolYear::plan(yearDates());

    expect($year->status())->toBe(SchoolYearStatus::Planned)
        ->and(termStatuses($year))->toBe([TermStatus::Pending, TermStatus::Pending, TermStatus::Pending]);
});

it('is named after the years it starts and ends in', function () {
    expect(SchoolYear::plan(yearDates())->name())->toBe('2026-2027');
});

it('must end after it starts', function () {
    expect(calendarViolations(fn () => yearDates('2026-09-16', '2026-09-16')))->toHaveKey('ends_on');
});

it('lasts 11 months at most', function () {
    expect(calendarViolations(fn () => yearDates('2026-09-16', '2027-08-16', [
        ['2026-09-16', '2026-12-15'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15'],
    ])))->toBe([])
        ->and(calendarViolations(fn () => yearDates('2026-09-16', '2027-08-17', [
            ['2026-09-16', '2026-12-15'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15'],
        ])))->toBe(['ends_on' => ['El año escolar no puede durar más de 11 meses.']]);
});

it('keeps every term inside the year', function () {
    expect(calendarViolations(fn () => yearDates(terms: [
        ['2026-09-15', '2026-12-15'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-16'],
    ])))->toHaveKeys(['terms.0.starts_on', 'terms.2.ends_on']);
});

it('refuses a term that ends before it starts', function () {
    expect(calendarViolations(fn () => yearDates(terms: [
        ['2026-09-16', '2026-12-15'], ['2027-03-31', '2027-01-07'], ['2027-04-12', '2027-07-15'],
    ])))->toHaveKey('terms.1.ends_on');
});

it('keeps the terms in order and apart', function () {
    expect(calendarViolations(fn () => yearDates(terms: [
        ['2026-09-16', '2027-01-07'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15'],
    ])))->toBe(['terms.1.starts_on' => ['El lapso 2 debe empezar después de que termine el lapso 1.']]);
});

it('allows days between terms and terms that do not cover the whole year', function () {
    expect(calendarViolations(fn () => yearDates('2026-09-01', '2027-07-31', [
        ['2026-09-16', '2026-12-15'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15'],
    ])))->toBe([]);
});

// CAL-R02 — activating and closing the year

it('opens its first term when activated', function () {
    expect(activeYear()->status())->toBe(SchoolYearStatus::Active)
        ->and(termStatuses(activeYear()))->toBe([TermStatus::Open, TermStatus::Pending, TermStatus::Pending]);
});

it('only activates a planned year', function () {
    expect(fn () => activeYear()->activate())->toThrow(BusinessRuleViolation::class);
});

it('closes, once confirmed, only with its three terms closed', function () {
    $year = activeYear();

    expect(calendarViolations(fn () => $year->close(confirmed: true)))->toBe(['status' => ['Cierra los tres lapsos antes de cerrar el año.']]);

    foreach (range(1, 3) as $term) {
        $year->closeTerm(confirmed: true);
    }

    expect(calendarViolations(fn () => $year->close(confirmed: false)))->toHaveKey('confirmation');

    $year->close(confirmed: true);
    expect($year->status())->toBe(SchoolYearStatus::Closed);
});

it('never comes back once closed', function () {
    $year = activeYear();
    foreach (range(1, 3) as $term) {
        $year->closeTerm(confirmed: true);
    }
    $year->close(confirmed: true);

    expect(fn () => $year->activate())->toThrow(BusinessRuleViolation::class)
        ->and(fn () => $year->reopenTerm(TermNumber::of(3), 'Corrección'))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => $year->changeDates(yearDates()))->toThrow(BusinessRuleViolation::class);
});

// CAL-R04 — terms

it('closes the open term and opens the next one, once confirmed', function () {
    $year = activeYear();

    expect(calendarViolations(fn () => $year->closeTerm(confirmed: false)))->toHaveKey('confirmation');

    $year->closeTerm(confirmed: true);

    expect(termStatuses($year))->toBe([TermStatus::Closed, TermStatus::Open, TermStatus::Pending]);
});

it('keeps exactly one term open while active, until all three are closed', function () {
    $year = activeYear();
    $year->closeTerm(confirmed: true);
    $year->closeTerm(confirmed: true);
    $year->closeTerm(confirmed: true);

    expect(termStatuses($year))->toBe([TermStatus::Closed, TermStatus::Closed, TermStatus::Closed])
        ->and(fn () => $year->closeTerm(confirmed: true))->toThrow(BusinessRuleViolation::class);
});

it('reopens the last closed term with a reason, leaving the open one not yet open', function () {
    $year = activeYear();
    $year->closeTerm(confirmed: true);

    $year->reopenTerm(TermNumber::of(1), 'Faltó cargar una nota');

    expect(termStatuses($year))->toBe([TermStatus::Open, TermStatus::Pending, TermStatus::Pending]);
});

it('reopens the third term once all three are closed', function () {
    $year = activeYear();
    foreach (range(1, 3) as $term) {
        $year->closeTerm(confirmed: true);
    }

    $year->reopenTerm(TermNumber::of(3), 'Corrección');

    expect(termStatuses($year))->toBe([TermStatus::Closed, TermStatus::Closed, TermStatus::Open]);
});

it('needs a reason to reopen a term', function (string $reason) {
    $year = activeYear();
    $year->closeTerm(confirmed: true);

    expect(calendarViolations(fn () => $year->reopenTerm(TermNumber::of(1), $reason)))->toHaveKey('reason');
})->with(['empty' => [''], 'blank' => ['   ']]);

it('only reopens the last closed term', function () {
    $year = activeYear();
    $year->closeTerm(confirmed: true);
    $year->closeTerm(confirmed: true);

    expect(calendarViolations(fn () => $year->reopenTerm(TermNumber::of(1), 'Corrección')))
        ->toBe(['term' => ['Solo se puede reabrir el último lapso cerrado.']]);
});

it('does not touch the terms of a planned year', function () {
    $year = SchoolYear::plan(yearDates());

    expect(fn () => $year->closeTerm(confirmed: true))->toThrow(BusinessRuleViolation::class)
        ->and(fn () => $year->reopenTerm(TermNumber::of(1), 'x'))->toThrow(BusinessRuleViolation::class);
});

// CAL-R03 — dates

it('changes its dates, and its name follows them', function () {
    $year = SchoolYear::plan(yearDates());

    $year->changeDates(yearDates('2027-09-16', '2028-07-15', [
        ['2027-09-16', '2027-12-15'], ['2028-01-07', '2028-03-31'], ['2028-04-12', '2028-07-15'],
    ]));

    expect($year->name())->toBe('2027-2028');
});

it('keeps the dates of a closed term', function () {
    $year = activeYear();
    $year->closeTerm(confirmed: true);

    expect(calendarViolations(fn () => $year->changeDates(yearDates(terms: [
        ['2026-09-16', '2026-12-18'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15'],
    ]))))->toBe(['terms.0.ends_on' => ['Las fechas de un lapso cerrado no se pueden cambiar.']])
        // The other terms can still change.
        ->and(calendarViolations(fn () => $year->changeDates(yearDates(terms: [
            ['2026-09-16', '2026-12-15'], ['2027-01-08', '2027-03-31'], ['2027-04-12', '2027-07-15'],
        ]))))->toBe([]);
});

// CAL-R09 — deleting

it('can only be deleted while planned', function () {
    expect(SchoolYear::plan(yearDates())->canBeDeleted())->toBeTrue()
        ->and(activeYear()->canBeDeleted())->toBeFalse();
});
