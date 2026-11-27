<?php

use AulaX\AcademicCalendar\Application\CalendarContext\CalendarContextQuery;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 3).'/Fixtures/AcademicCalendar/calendar_helpers.php';

/**
 * SPEC-013 NAV-R02.2, R02.3, R02.8: the active year and its open term for
 * the top bar, read fresh on every request, against the real database.
 */
test('names the active year and its open term', function () {
    tenant();
    storedSchoolYear(2025, 'closed', closedTerms: 3);
    storedSchoolYear(2027);
    $active = storedSchoolYear(2026, 'active', closedTerms: 1);

    $context = app(CalendarContextQuery::class)();

    expect($context->activeYearId)->toBe($active)
        ->and($context->activeYearName)->toBe('2026-2027')
        ->and($context->openTerm)->toBe(2)
        ->and($context->hasPlannedYear)->toBeTrue();
});

test('says when the active year has no open term', function () {
    tenant();
    storedSchoolYear(2026, 'active', closedTerms: 3);

    $context = app(CalendarContextQuery::class)();

    expect($context->activeYearName)->toBe('2026-2027')
        ->and($context->openTerm)->toBeNull()
        ->and($context->hasPlannedYear)->toBeFalse();
});

test('says when there is no active year, and whether one is planned', function () {
    tenant();
    storedSchoolYear(2026);

    $context = app(CalendarContextQuery::class)();

    expect($context->activeYearId)->toBeNull()
        ->and($context->activeYearName)->toBeNull()
        ->and($context->openTerm)->toBeNull()
        ->and($context->hasPlannedYear)->toBeTrue();
});

test('sees a year activated a moment ago, with one query', function () {
    tenant();
    $year = storedSchoolYear(2026);
    $query = app(CalendarContextQuery::class);
    expect($query()->activeYearId)->toBeNull();

    activateStoredSchoolYear($year);

    DB::enableQueryLog();
    $context = $query();

    expect($context->activeYearId)->toBe($year)
        ->and(DB::getQueryLog())->toHaveCount(1);
});

test('only reads the open institution', function () {
    $other = tenant();
    useTenant($other);
    storedSchoolYear(2026, 'active');
    useTenant(tenant());

    expect(app(CalendarContextQuery::class)()->activeYearId)->toBeNull();
})->group('rls');
