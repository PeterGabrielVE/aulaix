<?php

use AulaX\AcademicCalendar\Application\ActivateSchoolYear\ActivateSchoolYearHandler;
use AulaX\AcademicCalendar\Application\CloseSchoolYear\CloseSchoolYearHandler;
use AulaX\AcademicCalendar\Application\CloseTerm\CloseTermHandler;
use AulaX\AcademicCalendar\Application\ReopenTerm\ReopenTermHandler;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\SchoolYearSchedule;
use AulaX\AcademicCalendar\Domain\TermStatus;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\AcademicCalendar\CountingCalendarLock;
use Tests\Fixtures\AcademicCalendar\InMemorySchoolYearRepository;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-007 CAL-R02, R04, R07.1: the life of a school year and its terms.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->years = new InMemorySchoolYearRepository;
    $this->lock = new CountingCalendarLock($this->transactions);
    $this->audit = new RecordingAuditTrail($this->transactions);
});

function storedYear(int $start = 2026): int
{
    $end = $start + 1;

    return test()->years->save(SchoolYear::plan(SchoolYearDates::fromStrings("{$start}-09-16", "{$end}-07-15", [
        ["{$start}-09-16", "{$start}-12-15"], ["{$end}-01-07", "{$end}-03-31"], ["{$end}-04-12", "{$end}-07-15"],
    ])))->value;
}

function activate(int $id): void
{
    (new ActivateSchoolYearHandler(test()->years, new SchoolYearSchedule, test()->lock, test()->audit, test()->transactions))($id);
}

function closeTerm(int $id, bool $confirmed = true): void
{
    (new CloseTermHandler(test()->years, test()->lock, test()->audit, test()->transactions))($id, $confirmed);
}

it('activates a planned year, recorded under the lock', function () {
    $id = storedYear();

    activate($id);

    expect(test()->audit->types())->toBe(['calendar.school_year.activated'])
        ->and(test()->audit->changesOf(0)['status'])->toBe(['from' => 'planned', 'to' => 'active'])
        ->and(test()->lock->takenInsideTransaction)->toBe(1);
});

it('refuses to activate a year while another is active', function () {
    activate(storedYear(2025));

    expect(fn () => activate(storedYear(2026)))->toThrow(BusinessRuleViolation::class, 'Ya hay un año activo: ciérralo antes de activar otro.');
});

it('closes the open term once confirmed, recording which one', function () {
    $id = storedYear();
    activate($id);

    expect(fn () => closeTerm($id, confirmed: false))->toThrow(BusinessRuleViolation::class);

    closeTerm($id);

    expect(test()->audit->types())->toBe(['calendar.school_year.activated', 'calendar.term.closed'])
        ->and(test()->audit->changesOf(1))->toBe(['term' => ['from' => null, 'to' => 1], 'status' => ['from' => 'open', 'to' => 'closed']]);
});

it('reopens the last closed term, recording the reason', function () {
    $id = storedYear();
    activate($id);
    closeTerm($id);

    (new ReopenTermHandler(test()->years, test()->lock, test()->audit, test()->transactions))($id, 1, 'Faltó una nota');

    expect(array_map(fn ($term) => $term->status, test()->years->years[$id]->terms()))->toBe([TermStatus::Open, TermStatus::Pending, TermStatus::Pending])
        ->and(test()->audit->changesOf(2))->toBe(['term' => ['from' => null, 'to' => 1], 'status' => ['from' => 'closed', 'to' => 'open'], 'reason' => ['from' => null, 'to' => 'Faltó una nota']]);
});

it('closes the year once its three terms are closed and it is confirmed', function () {
    $id = storedYear();
    activate($id);
    closeTerm($id);
    closeTerm($id);
    closeTerm($id);

    (new CloseSchoolYearHandler(test()->years, test()->lock, test()->audit, test()->transactions))($id, true);

    expect(test()->audit->types())->toContain('calendar.school_year.closed');
});
