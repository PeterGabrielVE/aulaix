<?php

use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentCommand;
use AulaX\Enrollment\Application\AnnulEnrollment\AnnulEnrollmentHandler;
use AulaX\Enrollment\Application\EnrollmentNotFound;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Domain\AnnulmentNotAllowed;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Shared\Domain\BusinessRuleViolation;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R14.1 – R14.4, R12.1: annulling an enrollment made by
 * mistake, kept with its reason.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
    $scene = $this->scene;
    $this->enrollmentId = (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand(5, 2, 10, '2026-09-16', null, null, 3),
    )->enrollmentId;
    $this->scene->audit->recorded = [];
});

function annulEnrollment(?string $reason = 'duplicate', ?string $note = null, ?int $enrollment = null): void
{
    $scene = test()->scene;
    (new AnnulEnrollmentHandler($scene->enrollments, $scene->calendar, $scene->graded(), $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new AnnulEnrollmentCommand($enrollment ?? test()->enrollmentId, $reason, $note, 3),
    );
}

it('a manager annuls within 48 hours: kept, ended and free to enroll again, recorded', function () {
    $this->scene->clock->now = new DateTimeImmutable('2026-10-17 09:00');
    annulEnrollment('other', 'Estudiante equivocado');
    $annulled = $this->scene->enrollments->byId(EnrollmentId::fromInt($this->enrollmentId));

    expect($annulled?->period()->endReason)->toBe(EndReason::Annulled)
        ->and($annulled?->annulment()?->note)->toBe('Estudiante equivocado')
        ->and($annulled?->annulment()?->annulledBy)->toBe(3)
        ->and($this->scene->enrollments->currentOf(5, 2))->toBeNull()
        ->and($this->scene->enrollments->currentCountIn(10))->toBe(0)
        ->and($this->scene->calendar->lockedInsideTransaction)->toBe(2)
        ->and($this->scene->audit->types())->toBe(['enrollment.enrollment.annulled'])
        ->and($this->scene->audit->recorded[0]['inTransaction'])->toBeTrue();
});

it('after 48 hours, only a directive annuls', function () {
    $this->scene->clock->now = new DateTimeImmutable('2026-10-17 09:01');
    $this->scene->permissions->approve = false;

    expect(fn () => annulEnrollment())->toThrow(AnnulmentNotAllowed::class, 'Pasaron más de 48 horas: la anula un directivo.');

    $this->scene->permissions->approve = true;
    annulEnrollment();

    expect($this->scene->enrollments->currentOf(5, 2))->toBeNull();
});

it('with grades, only a directive annuls', function () {
    $this->scene->graded = [$this->enrollmentId];
    $this->scene->permissions->approve = false;

    expect(fn () => annulEnrollment())->toThrow(AnnulmentNotAllowed::class, 'Ya tiene notas: la anula un directivo.');
});

it('needs a reason, and a note for another one', function () {
    expect(fn () => annulEnrollment(null))->toThrow(BusinessRuleViolation::class, 'Indica el motivo de la anulación.')
        ->and(fn () => annulEnrollment('cualquiera'))->toThrow(BusinessRuleViolation::class, 'Indica el motivo de la anulación.')
        ->and(fn () => annulEnrollment('other'))->toThrow(BusinessRuleViolation::class, 'Describe el motivo de la anulación.');
});

it('answers not found for an enrollment it does not have, and refuses whoever may not manage', function () {
    expect(fn () => annulEnrollment(enrollment: 99))->toThrow(EnrollmentNotFound::class);

    $this->scene->permissions->manage = false;

    expect(fn () => annulEnrollment())->toThrow(AuthorizationDenied::class);
});
