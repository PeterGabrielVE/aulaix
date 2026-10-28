<?php

use AulaX\AcademicCalendar\Application\CloseSchoolYear\CloseSchoolYearHandler;
use AulaX\AcademicCalendar\Application\DeleteSchoolYear\DeleteSchoolYearHandler;
use AulaX\AcademicCalendar\Application\Port\SchoolYearGuard;
use AulaX\AcademicCalendar\Application\SchoolYearGuards;
use AulaX\AcademicCalendar\Domain\SchoolYear;
use AulaX\AcademicCalendar\Domain\SchoolYearDates;
use AulaX\AcademicCalendar\Domain\SchoolYearStatus;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\AcademicCalendar\CountingCalendarLock;
use Tests\Fixtures\AcademicCalendar\InMemorySchoolYearRepository;
use Tests\Fixtures\Audit\RecordingAuditTrail;
use Tests\Fixtures\Shared\InlineTransactionManager;

/**
 * SPEC-010 MAT-R16.2 (ADR 0012): the year is not closed while another
 * module says students lack their result, nor deleted while it has
 * enrollments; asked under the calendar lock.
 */
beforeEach(function () {
    $this->transactions = new InlineTransactionManager;
    $this->years = new InMemorySchoolYearRepository;
    $this->lock = new CountingCalendarLock($this->transactions);
    $this->audit = new RecordingAuditTrail($this->transactions);
    $this->guard = new class($this->lock) implements SchoolYearGuard
    {
        public int $unresolved = 0;

        public bool $inUse = false;

        public int $askedAfterLock = 0;

        public function __construct(private readonly CountingCalendarLock $lock) {}

        public function unresolvedResults(int $schoolYearId): int
        {
            $this->askedAfterLock = $this->lock->takenInsideTransaction;

            return $this->unresolved;
        }

        public function hasEnrollments(int $schoolYearId): bool
        {
            return $this->inUse;
        }
    };
});

function guardedYear(bool $activeWithClosedTerms): int
{
    $year = SchoolYear::plan(SchoolYearDates::fromStrings('2026-09-16', '2027-07-15', [
        ['2026-09-16', '2026-12-15'], ['2027-01-07', '2027-03-31'], ['2027-04-12', '2027-07-15'],
    ]));

    if ($activeWithClosedTerms) {
        $year->activate();
        $year->closeTerm(confirmed: true);
        $year->closeTerm(confirmed: true);
        $year->closeTerm(confirmed: true);
    }

    return test()->years->save($year)->value;
}

function guards(): SchoolYearGuards
{
    return new SchoolYearGuards([test()->guard]);
}

it('does not close the year while students lack their result, and says how many', function () {
    $id = guardedYear(activeWithClosedTerms: true);
    $this->guard->unresolved = 3;

    expect(fn () => (new CloseSchoolYearHandler($this->years, $this->lock, $this->audit, $this->transactions, guards()))($id, true))
        ->toThrow(BusinessRuleViolation::class, 'Hay 3 estudiantes sin resultado de prosecución: regístralo o retíralos antes de cerrar el año.')
        ->and($this->years->years[$id]->status())->toBe(SchoolYearStatus::Active)
        ->and($this->guard->askedAfterLock)->toBeGreaterThan(0);

    $this->guard->unresolved = 0;
    (new CloseSchoolYearHandler($this->years, $this->lock, $this->audit, $this->transactions, guards()))($id, true);

    expect($this->years->years[$id]->status())->toBe(SchoolYearStatus::Closed);
});

it('does not delete a planned year that has enrollments', function () {
    $id = guardedYear(activeWithClosedTerms: false);
    $this->guard->inUse = true;

    expect(fn () => (new DeleteSchoolYearHandler($this->years, $this->lock, $this->audit, $this->transactions, guards()))($id, true))
        ->toThrow(BusinessRuleViolation::class, 'El año tiene inscripciones: no se puede eliminar.')
        ->and($this->years->years)->toHaveKey($id);
});

it('with no guard registered, closes and deletes as before', function () {
    $guards = new SchoolYearGuards([]);

    expect($guards->unresolvedResults(1))->toBe(0)
        ->and($guards->hasEnrollments(1))->toBeFalse();
});
