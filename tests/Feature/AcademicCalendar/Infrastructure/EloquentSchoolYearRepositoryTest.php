<?php

use AulaX\AcademicCalendar\Application\CalendarLock;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\SchoolYearRepository;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\AcademicCalendar\Domain\TermStatus;
use AulaX\Shared\Application\TransactionManager;
use AulaX\Shared\Domain\Conflict;
use Illuminate\Support\Facades\DB;

/**
 * SPEC-007: a school year goes to the database and back with its terms.
 */
function schoolYears(): SchoolYearRepository
{
    return app(SchoolYearRepository::class);
}

function plannedYear(int $start = 2026): SchoolYear
{
    $end = $start + 1;

    return SchoolYear::plan(SchoolYearDates::fromStrings("{$start}-09-16", "{$end}-07-15", [
        ["{$start}-09-16", "{$start}-12-15"], ["{$end}-01-07", "{$end}-03-31"], ["{$end}-04-12", "{$end}-07-15"],
    ]));
}

it('stores a new year with its three terms and reads it back', function () {
    tenant();
    $id = schoolYears()->save(plannedYear());

    $year = schoolYears()->byId($id);

    expect($year->name())->toBe('2026-2027')
        ->and($year->status())->toBe(SchoolYearStatus::Planned)
        ->and(array_map(fn ($term) => $term->dates->start->format('Y-m-d'), $year->terms()))->toBe(['2026-09-16', '2027-01-07', '2027-04-12']);
});

it('stores every change of the year and its terms', function () {
    tenant();
    $id = schoolYears()->save(plannedYear());
    $year = schoolYears()->byId($id);
    $year->activate();
    $year->closeTerm(confirmed: true);

    schoolYears()->save($year);

    $stored = schoolYears()->byId($id);
    expect($stored->status())->toBe(SchoolYearStatus::Active)
        ->and(array_map(fn ($term) => $term->status, $stored->terms()))->toBe([TermStatus::Closed, TermStatus::Open, TermStatus::Pending]);
});

it('lists the institution\'s other years and deletes a year with its terms', function () {
    tenant();
    $current = schoolYears()->save(plannedYear(2025));
    $year = schoolYears()->byId($current);
    $year->activate();
    schoolYears()->save($year);
    $next = schoolYears()->save(plannedYear(2026));

    expect(array_map(fn (SchoolYear $other) => $other->id()->value, schoolYears()->othersOf($next)))->toBe([$current->value]);

    schoolYears()->delete($next);

    expect(schoolYears()->byId($next))->toBeNull()
        ->and(DB::table('terms')->where('school_year_id', $next->value)->exists())->toBeFalse();
});

it('does not find a year of another institution', function () {
    tenant();
    $foreign = schoolYears()->save(plannedYear());
    tenant();

    expect(schoolYears()->byId($foreign))->toBeNull();
});

it('takes the calendar lock of the institution inside the transaction', function () {
    tenant();

    // Held until the transaction ends (here, the test transaction).
    $held = app(TransactionManager::class)->run(function () {
        app(CalendarLock::class)->lockInstitutionCalendar();

        return DB::table('pg_locks')->where('locktype', 'advisory')->where('pid', DB::raw('pg_backend_pid()'))->count();
    });

    expect($held)->toBe(1);
});

it('turns a broken database rule into a conflict, saving nothing', function () {
    tenant();
    $first = schoolYears()->byId(schoolYears()->save(plannedYear(2025)));
    $first->activate();
    schoolYears()->save($first);

    // As if two managers had activated two years at once, past every check.
    $second = schoolYears()->byId(schoolYears()->save(plannedYear(2026)));
    $second->activate();

    expect(fn () => DB::transaction(fn () => schoolYears()->save($second)))
        ->toThrow(Conflict::class, 'Otra persona acaba de cambiar el calendario. Vuelve a cargar la página.');
});
