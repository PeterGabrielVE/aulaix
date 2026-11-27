<?php

use AulaX\AcademicCalendar\Application\CloseTerm\CloseTermHandler;
use AulaX\AcademicCalendar\Application\Port\TermClosingGuard;
use AulaX\AcademicCalendar\Application\TermClosingGuards;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\TermStatus;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\AcademicCalendar\CountingCalendarLock;
use Tests\Fixtures\AcademicCalendar\InMemorySchoolYearRepository;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-011 EVA-R12.2 – R12.4 (ADR 0012): a term does not close while another
 * module says grades are pending, unless closed by exception with a
 * justification, which first fills what is pending; asked under the
 * calendar lock.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->years = new InMemorySchoolYearRepository;
    $this->lock = new CountingCalendarLock($this->transactions);
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->guard = new class($this->lock) implements TermClosingGuard
    {
        public int $pending = 0;

        public int $askedAfterLock = 0;

        /** @var list<array{int, int, string}> */
        public array $filled = [];

        public function __construct(private readonly CountingCalendarLock $lock) {}

        public function pendingGrades(int $schoolYearId, int $term): int
        {
            $this->askedAfterLock = $this->lock->takenInsideTransaction;

            return $this->pending;
        }

        public function fillPending(int $schoolYearId, int $term, string $justification): void
        {
            $this->filled[] = [$schoolYearId, $term, $justification];
            $this->pending = 0;
        }
    };

    $year = SchoolYear::plan(SchoolYearDates::fromStrings('2026-09-16', '2027-07-15', [
        ['2026-09-16', '2026-12-15'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15'],
    ]));
    $year->activate();
    $this->year = $this->years->save($year)->value;
});

function closeGuardedTerm(?string $justification = null): void
{
    $test = test();
    (new CloseTermHandler($test->years, $test->lock, $test->audit, $test->transactions, new TermClosingGuards([$test->guard])))($test->year, true, $justification);
}

function firstTermStatus(): TermStatus
{
    return test()->years->years[test()->year]->terms()[0]->status;
}

it('does not close a term with pending grades, and says how many', function () {
    $this->guard->pending = 4;

    expect(fn () => closeGuardedTerm())->toThrow(BusinessRuleViolation::class, 'Hay 4 notas pendientes: complétalas o cierra por excepción con una justificación.')
        ->and(firstTermStatus())->toBe(TermStatus::Open)
        ->and($this->guard->askedAfterLock)->toBeGreaterThan(0)
        ->and($this->guard->filled)->toBe([]);
});

it('closes by exception only with a justification, filling what is pending first', function () {
    $this->guard->pending = 4;

    expect(fn () => closeGuardedTerm('   '))->toThrow(BusinessRuleViolation::class, 'Indica la justificación para cerrar por excepción.')
        ->and(firstTermStatus())->toBe(TermStatus::Open);

    closeGuardedTerm('El docente está de reposo médico.');

    expect($this->guard->filled)->toBe([[$this->year, 1, 'El docente está de reposo médico.']])
        ->and(firstTermStatus())->toBe(TermStatus::Closed);
});

it('closes a term with nothing pending, without filling anything', function () {
    closeGuardedTerm();

    expect(firstTermStatus())->toBe(TermStatus::Closed)
        ->and($this->guard->filled)->toBe([]);
});
