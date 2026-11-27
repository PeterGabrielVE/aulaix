<?php

use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\Hooks\EndEnrollmentsOnWithdrawal;
use AulaX\Enrollment\Application\Hooks\UnresolvedResultsGuard;
use AulaX\Enrollment\Application\Roster\PendingResultsQuery;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\Outcome;
use AulaX\Enrollment\Domain\PromotionResult;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R06.10, R16.1, R16.2, R16.4: who still lacks the result of
 * the year, by section, before the year closes.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
    $this->scene->students->add(5, '2018-03-10', name: 'Pérez, Ana');
    $this->scene->students->add(6, '2018-04-10', name: 'Álvarez, Luis');
    $this->scene->students->add(7, '2018-05-10', name: 'Mora, Eva');
    $this->ana = pendingEnroll(5, 10);
    $this->luis = pendingEnroll(6, 10);
    $this->eva = pendingEnroll(7, 11);
});

function pendingEnroll(int $student, int $section): int
{
    $scene = test()->scene;

    return (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand($student, 2, $section, '2026-09-16', null, null, 3),
    )->enrollmentId;
}

function recordPromotion(int $enrollmentId): void
{
    $enrollments = test()->scene->enrollments;
    $enrollment = $enrollments->byId(EnrollmentId::fromInt($enrollmentId));
    $enrollment->recordResult(PromotionResult::of(Outcome::Promoted, []), [], new DateTimeImmutable('2027-07-10'), 3);
    $enrollments->save($enrollment);
}

it('lists, by section, the students with a current enrollment and no result', function () {
    recordPromotion($this->eva);
    $pending = (new PendingResultsQuery($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->permissions))(2);

    expect(array_map(fn ($section) => [$section->sectionId, $section->gradeName, $section->letter, $section->students], $pending))->toBe([
        [10, '3.er grado', 'A', ['Álvarez, Luis', 'Pérez, Ana']],
    ]);
});

it('shows only how many to whoever may not see students', function () {
    $this->scene->permissions->view = false;
    $pending = (new PendingResultsQuery($this->scene->enrollments, $this->scene->students, $this->scene->structure, $this->scene->permissions))(2);

    expect($pending[0]->count)->toBe(2)
        ->and($pending[0]->students)->toBe([]);
});

it('tells the calendar how many lack their result; a withdrawn student no longer counts', function () {
    $guard = new UnresolvedResultsGuard($this->scene->enrollments);

    expect($guard->unresolvedResults(2))->toBe(3)
        ->and($guard->hasEnrollments(2))->toBeTrue()
        ->and($guard->hasEnrollments(3))->toBeFalse();

    $this->scene->transactions->run(fn () => (new EndEnrollmentsOnWithdrawal($this->scene->enrollments, $this->scene->calendar, $this->scene->audit))
        ->studentWithdrawn(5, new DateTimeImmutable('2027-06-01'), false));
    recordPromotion($this->eva);

    expect($guard->unresolvedResults(2))->toBe(1)
        ->and($guard->hasEnrollments(2))->toBeTrue();
});
