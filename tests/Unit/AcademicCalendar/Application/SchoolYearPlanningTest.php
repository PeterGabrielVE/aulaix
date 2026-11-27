<?php

use AulaX\AcademicCalendar\Application\ChangeSchoolYearDates\ChangeSchoolYearDatesCommand;
use AulaX\AcademicCalendar\Application\ChangeSchoolYearDates\ChangeSchoolYearDatesHandler;
use AulaX\AcademicCalendar\Application\CreateSchoolYear\CreateSchoolYearCommand;
use AulaX\AcademicCalendar\Application\CreateSchoolYear\CreateSchoolYearHandler;
use AulaX\AcademicCalendar\Application\DeleteSchoolYear\DeleteSchoolYearHandler;
use AulaX\AcademicCalendar\Application\SchoolYearNotFound;
use AulaX\AcademicCalendar\Domain\SchoolYearId;
use AulaX\AcademicCalendar\Domain\SchoolYearSchedule;
use AulaX\Shared\Domain\BusinessRuleViolation;
use AulaX\Shared\Domain\BusinessRuleViolations;
use Tests\Fixtures\AcademicCalendar\CountingCalendarLock;
use Tests\Fixtures\AcademicCalendar\InMemorySchoolYearRepository;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-007 CAL-R01, R03, R09, R07.1: planning school years, with the rules
 * across years, the calendar lock and the audit log.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->years = new InMemorySchoolYearRepository;
    $this->lock = new CountingCalendarLock($this->transactions);
    $this->audit = new RecordingAuditTrail($this->transactions);
});

function planning(): object
{
    $test = test();

    return new class($test)
    {
        public function __construct(private readonly object $test) {}

        public function create(int $start = 2026): int
        {
            $end = $start + 1;

            return (new CreateSchoolYearHandler($this->test->years, new SchoolYearSchedule, $this->test->lock, $this->test->audit, $this->test->transactions))(
                new CreateSchoolYearCommand("{$start}-09-16", "{$end}-07-15", [
                    ["{$start}-09-16", "{$start}-12-15"], ["{$end}-01-07", "{$end}-03-31"], ["{$end}-04-12", "{$end}-07-15"],
                ]),
            );
        }

        public function changeDates(int $id, string $start, string $end, array $terms): void
        {
            (new ChangeSchoolYearDatesHandler($this->test->years, new SchoolYearSchedule, $this->test->lock, $this->test->audit, $this->test->transactions))(
                new ChangeSchoolYearDatesCommand($id, $start, $end, $terms),
            );
        }

        public function delete(int $id, bool $confirmed = true): void
        {
            (new DeleteSchoolYearHandler($this->test->years, $this->test->lock, $this->test->audit, $this->test->transactions))($id, $confirmed);
        }
    };
}

it('creates a planned year, under the lock, recorded in the same transaction', function () {
    $id = planning()->create();

    expect($this->years->byId(SchoolYearId::fromInt($id))->name())->toBe('2026-2027')
        ->and($this->lock->takenInsideTransaction)->toBe(1)
        ->and($this->audit->types())->toBe(['calendar.school_year.created'])
        ->and($this->audit->recorded[0]['inTransaction'])->toBeTrue()
        ->and($this->audit->changesOf(0)['starts_on'])->toBe(['from' => null, 'to' => '2026-09-16']);
});

it('refuses invalid dates without saving or recording anything', function () {
    expect(fn () => (new CreateSchoolYearHandler($this->years, new SchoolYearSchedule, $this->lock, $this->audit, $this->transactions))(
        new CreateSchoolYearCommand('2026-09-16', '2026-09-01', [['2026-09-16', '2026-12-15'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15']]),
    ))->toThrow(BusinessRuleViolations::class);

    expect($this->years->years)->toBe([])->and($this->audit->recorded)->toBe([]);
});

it('refuses a second planned year', function () {
    planning()->create(2026);

    expect(fn () => planning()->create(2027))->toThrow(BusinessRuleViolation::class, 'Ya hay un año planificado en este plantel.');
});

it('records only the dates that changed', function () {
    $id = planning()->create();

    planning()->changeDates($id, '2026-09-16', '2027-07-15', [
        ['2026-09-16', '2026-12-18'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15'],
    ]);

    expect($this->audit->types())->toBe(['calendar.school_year.created', 'calendar.school_year.dates_changed'])
        ->and($this->audit->changesOf(1))->toBe(['terms.0.ends_on' => ['from' => '2026-12-15', 'to' => '2026-12-18']]);
});

it('refuses new dates that overlap another year', function () {
    $current = planning()->create(2025);
    $this->years->byId(SchoolYearId::fromInt($current))->activate();
    $next = planning()->create(2026);

    expect(fn () => planning()->changeDates($next, '2026-07-01', '2027-05-31', [
        ['2026-09-16', '2026-12-15'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-05-31'],
    ]))->toThrow(BusinessRuleViolation::class, 'Las fechas se solapan con el año 2025-2026.');
});

it('deletes a planned year once confirmed, and records it', function () {
    $id = planning()->create();

    expect(fn () => planning()->delete($id, confirmed: false))->toThrow(BusinessRuleViolation::class);

    planning()->delete($id);

    expect($this->years->years)->toBe([])
        ->and($this->audit->types())->toBe(['calendar.school_year.created', 'calendar.school_year.deleted']);
});

it('does not delete a year that was activated', function () {
    $id = planning()->create();
    $this->years->byId(SchoolYearId::fromInt($id))->activate();

    expect(fn () => planning()->delete($id))->toThrow(BusinessRuleViolation::class, 'Solo se puede eliminar un año planificado.');
});

it('answers an unknown year as not found', function () {
    expect(fn () => planning()->delete(99))->toThrow(SchoolYearNotFound::class);
});
