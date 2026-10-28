<?php

use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Hooks\EndEnrollmentsOnWithdrawal;
use AulaX\Enrollment\Application\Hooks\EnrollmentRemovalGuard;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\EnrollmentId;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R05.1 – R05.4, R10.1, R10.2, R12.1 (ADR 0012): what
 * Enrollment does inside the writes of Students and AcademicStructure.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
    $this->scene->calendar->year(3, 2027, 'planned');
    $this->scene->structure->section(30, 3, 'primaria_4');
});

function enrolledFor(int $year, int $section, string $date = '2026-09-16'): int
{
    $scene = test()->scene;

    return (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand(5, $year, $section, $date, null, null, 3),
    )->enrollmentId;
}

function withdrawnFromScene(string $on, bool $transferred = false): void
{
    $scene = test()->scene;
    $scene->transactions->run(fn () => (new EndEnrollmentsOnWithdrawal($scene->enrollments, $scene->calendar, $scene->audit))->studentWithdrawn(5, new DateTimeImmutable($on), $transferred));
}

it('a withdrawal ends the current enrollments on its date; one of a year not started yet, on its start', function () {
    $now = enrolledFor(2, 10);
    $this->scene->clock->now = new DateTimeImmutable('2027-09-20 09:00');
    $next = enrolledFor(3, 30, '2027-09-16');
    $this->scene->audit->recorded = [];

    withdrawnFromScene('2026-11-03');

    expect($this->scene->enrollments->byId(EnrollmentId::fromInt($now))?->period()->endsOn?->format('Y-m-d'))->toBe('2026-11-03')
        ->and($this->scene->enrollments->byId(EnrollmentId::fromInt($now))?->period()->endReason)->toBe(EndReason::Withdrawal)
        ->and($this->scene->enrollments->byId(EnrollmentId::fromInt($next))?->period()->endsOn?->format('Y-m-d'))->toBe('2027-09-16')
        ->and($this->scene->enrollments->currentOfStudent(5))->toBe([])
        ->and($this->scene->calendar->lockedInsideTransaction)->toBe(3)
        ->and($this->scene->audit->types())->toBe(['enrollment.enrollment.ended', 'enrollment.enrollment.ended']);
});

it('a transfer ends them as a transfer', function () {
    $id = enrolledFor(2, 10);

    withdrawnFromScene('2026-10-10', transferred: true);

    expect($this->scene->enrollments->byId(EnrollmentId::fromInt($id))?->period()->endReason)->toBe(EndReason::Transfer);
});

it('a reinstated student, or one that arrived by transfer, has no current enrollment until it is enrolled', function () {
    enrolledFor(2, 10);
    withdrawnFromScene('2026-10-10');

    expect($this->scene->enrollments->currentOf(5, 2))->toBeNull();

    $this->scene->students->add(6, '2018-01-01');

    expect($this->scene->enrollments->currentOfStudent(6))->toBe([]);
});

it('a student with no enrollment is withdrawn with nothing else to do', function () {
    withdrawnFromScene('2026-10-10');

    expect($this->scene->audit->types())->toBe([]);
});

it('tells the structure the current enrollments of a section, and whether it ever had any', function () {
    $guard = new EnrollmentRemovalGuard($this->scene->enrollments);
    enrolledFor(2, 10);

    expect($guard->enrolledIn(10))->toBe(1)
        ->and($guard->hasEnrollments(10))->toBeTrue()
        ->and($guard->hasEnrollments(11))->toBeFalse()
        ->and($guard->subjectsInUse(2, 'primaria_3', [1, 2]))->toBe([]);

    withdrawnFromScene('2026-10-10');

    expect($guard->enrolledIn(10))->toBe(0)
        ->and($guard->hasEnrollments(10))->toBeTrue();
});
