<?php

use AulaX\AcademicCalendar\Application\ListSchoolYears\ListSchoolYearsHandler;
use AulaX\AcademicCalendar\Application\ListSchoolYears\SchoolYearView;
use AulaX\AcademicCalendar\Application\TermEditability\TermEditabilityQuery;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-007 CAL-R05, R06.1, NF03: the reads other modules and the screen use,
 * against the real database.
 */
function savedYear(int $start, string $state = 'planned', int $closedTerms = 0): int
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

function editable(int $year, int $term, bool $permission = false): bool
{
    return app(TermEditabilityQuery::class)($year, $term, $permission);
}

test('the open term admits grade changes; closed and not yet open ones only with the permission', function () {
    tenant();
    $year = savedYear(2026, 'active', closedTerms: 1);

    expect(editable($year, 2))->toBeTrue()
        ->and(editable($year, 1))->toBeFalse()
        ->and(editable($year, 1, permission: true))->toBeTrue()
        ->and(editable($year, 3))->toBeFalse();
});

test('no term of a closed year admits changes, not even with the permission', function () {
    tenant();
    $year = savedYear(2025, 'closed', closedTerms: 3);

    expect(editable($year, 3, permission: true))->toBeFalse();
});

test('a year of another institution, or one that does not exist, admits nothing', function () {
    tenant();
    $foreign = savedYear(2026, 'active');
    tenant();

    expect(editable($foreign, 1, permission: true))->toBeFalse()
        ->and(editable(999_999, 1, permission: true))->toBeFalse();
});

test('the screen lists the years, newest first, with their terms', function () {
    tenant();
    savedYear(2025, 'closed', closedTerms: 3);
    savedYear(2026, 'active', closedTerms: 1);

    $years = app(ListSchoolYearsHandler::class)();

    expect(array_map(fn (SchoolYearView $year) => [$year->name, $year->status], $years))->toBe([['2026-2027', 'active'], ['2025-2026', 'closed']])
        ->and(array_map(fn ($term) => $term->status, $years[0]->terms))->toBe(['closed', 'open', 'pending'])
        ->and($years[0]->terms[0]->startsOn)->toBe('2026-09-16');
});

test('the editability check answers within 10 ms (p95)', function () {
    tenant();
    $years = array_map(fn (int $start) => savedYear($start, $start === 2099 ? 'active' : 'planned'), [2099]);
    foreach (range(1900, 2098) as $start) {
        DB::table('school_years')->insert(['starts_on' => "{$start}-09-16", 'ends_on' => ($start + 1).'-07-15', 'status' => 'closed']);
    }

    $timings = [];
    foreach (range(1, 200) as $call) {
        $began = hrtime(true);
        editable($years[0], 1);
        $timings[] = (hrtime(true) - $began) / 1e6;
    }
    sort($timings);

    expect($timings[(int) floor(count($timings) * 0.95)])->toBeLessThan(10.0);
})->group('perf');
