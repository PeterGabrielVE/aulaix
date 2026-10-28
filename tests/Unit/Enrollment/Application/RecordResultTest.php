<?php

use AulaX\Enrollment\Application\EnrollmentNotFound;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentCommand;
use AulaX\Enrollment\Application\EnrollStudent\EnrollStudentHandler;
use AulaX\Enrollment\Application\RecordResult\RecordResultCommand;
use AulaX\Enrollment\Application\RecordResult\RecordResultHandler;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\Outcome;
use AulaX\Enrollment\Domain\ResultNotAllowed;
use AulaX\Shared\Domain\AuthorizationDenied;
use Tests\Fixtures\Enrollment\EnrollmentScene;

/**
 * SPEC-010 MAT-R06.1, R06.2, R06.8, R06.9, R16.3, R12.1: the result of each
 * student at the end of the year, under the calendar lock and recorded.
 */
beforeEach(function () {
    $this->scene = new EnrollmentScene;
    $this->scene->calendar->years[2]['lastTermClosed'] = true;
    $this->enrollmentId = resultEnroll(5, 2, 10);
    $this->scene->audit->recorded = [];
});

function resultEnroll(int $student, int $year, int $section, string $date = '2026-09-16'): int
{
    $scene = test()->scene;

    return (new EnrollStudentHandler($scene->enrollments, $scene->students, $scene->structure, $scene->calendar, $scene->ages, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new EnrollStudentCommand($student, $year, $section, $date, null, null, 3),
    )->enrollmentId;
}

function recordResult(?string $outcome, array $pending = [], ?int $enrollment = null, int $actor = 3): void
{
    $scene = test()->scene;
    (new RecordResultHandler($scene->enrollments, $scene->structure, $scene->calendar, $scene->permissions, $scene->clock, $scene->audit, $scene->transactions))(
        new RecordResultCommand($enrollment ?? test()->enrollmentId, $outcome, $pending, $actor),
    );
}

it('records the result of a student once the third term is closed, under the calendar lock, recorded', function () {
    recordResult('lost_by_absence');
    $enrollment = $this->scene->enrollments->byId(EnrollmentId::fromInt($this->enrollmentId));

    expect($enrollment?->result()?->outcome)->toBe(Outcome::LostByAbsence)
        ->and($enrollment?->resultRecordedBy())->toBe(3)
        ->and($this->scene->calendar->lockedInsideTransaction)->toBe(2)
        ->and($this->scene->audit->types())->toBe(['enrollment.enrollment.result_recorded'])
        ->and($this->scene->audit->changesOf(0)['outcome'])->toBe(['from' => null, 'to' => 'lost_by_absence'])
        ->and($this->scene->audit->recorded[0]['inTransaction'])->toBeTrue();
});

it('only with the year active and its third term closed', function () {
    $this->scene->calendar->years[2]['lastTermClosed'] = false;

    expect(fn () => recordResult('promoted'))->toThrow(ResultNotAllowed::class, 'El resultado se registra con el año activo y su tercer lapso cerrado.');

    $this->scene->calendar->years[2] = ['status' => 'closed', 'start' => '2026-09-16', 'lastTermClosed' => true];

    expect(fn () => recordResult('promoted'))->toThrow(ResultNotAllowed::class, 'El resultado se registra con el año activo y su tercer lapso cerrado.');
});

it('needs a known result, and checks the pending subjects against the curriculum of the grade', function () {
    $this->scene->structure->section(40, 2, 'media_3', 'A');
    $this->scene->students->add(6, '2012-03-10');
    $media = resultEnroll(6, 2, 40);
    $this->scene->structure->curricula['2:media_3'] = [4, 5, 6];

    expect(fn () => recordResult('aprobado'))->toThrow(ResultNotAllowed::class, 'Indica el resultado.')
        ->and(fn () => recordResult('promoted_with_pending', [9], $media))->toThrow(ResultNotAllowed::class, 'Las materias pendientes deben ser del pensum del grado.');

    recordResult('promoted_with_pending', [4, 6], $media);

    expect($this->scene->enrollments->byId(EnrollmentId::fromInt($media))?->result()?->pendingSubjects)->toBe([4, 6]);
});

it('corrects the result before enrolling in the next year, keeping the old one in the audit log, but not after', function () {
    recordResult('repeats');
    recordResult('promoted', actor: 9);

    expect($this->scene->audit->changesOf(1)['outcome'])->toBe(['from' => 'repeats', 'to' => 'promoted'])
        ->and($this->scene->enrollments->byId(EnrollmentId::fromInt($this->enrollmentId))?->resultRecordedBy())->toBe(9);

    $this->scene->calendar->year(3, 2027, 'planned');
    $this->scene->structure->section(30, 3, 'primaria_4');
    $this->scene->clock->now = new DateTimeImmutable('2027-09-20 09:00');
    resultEnroll(5, 3, 30, '2027-09-16');

    expect(fn () => recordResult('repeats'))->toThrow(ResultNotAllowed::class, 'El estudiante ya está inscrito en el año siguiente: su resultado no cambia.');
});

it('answers not found for an enrollment it does not have, and refuses whoever may not manage', function () {
    expect(fn () => recordResult('promoted', enrollment: 99))->toThrow(EnrollmentNotFound::class);

    $this->scene->permissions->manage = false;

    expect(fn () => recordResult('promoted'))->toThrow(AuthorizationDenied::class);
});
