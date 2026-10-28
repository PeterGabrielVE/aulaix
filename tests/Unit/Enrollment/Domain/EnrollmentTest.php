<?php

use AulaX\Enrollment\Domain\AgeException;
use AulaX\Enrollment\Domain\AnnulmentNotAllowed;
use AulaX\Enrollment\Domain\AnnulmentReason;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\Placement;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-010 MAT-R01.1, R01.6, R04.1 – R04.3, R04.7, R05.1, R05.2, R14.1,
 * R14.3, R14.4: an enrollment, from its start to its end.
 */
function sectionA(): Placement
{
    return Placement::of(10, 'primaria_3', 'primaria');
}

function onDay(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date);
}

function enrolled(string $on = '2026-09-16', string $registeredAt = '2026-09-16 08:00'): Enrollment
{
    $enrollment = Enrollment::enroll(
        studentId: 5,
        schoolYearId: 2,
        placement: sectionA(),
        startsOn: onDay($on),
        today: onDay('2026-10-15'),
        overCapacityReason: null,
        ageException: null,
        registeredAt: onDay($registeredAt),
        registeredBy: 3,
    );
    $enrollment->identify(EnrollmentId::fromInt(40));

    return $enrollment;
}

it('enrolls a student in a section of a school year, from a date', function () {
    $exception = AgeException::of('Repitió dos veces', approvedBy: 9, byDirective: true);
    $enrollment = Enrollment::enroll(5, 2, sectionA(), onDay('2026-09-16'), onDay('2026-09-16'), 'Hermanos', $exception, onDay('2026-09-16 08:00'), 3);

    expect($enrollment->id())->toBeNull()
        ->and($enrollment->studentId())->toBe(5)
        ->and($enrollment->schoolYearId())->toBe(2)
        ->and($enrollment->placement())->toEqual(sectionA())
        ->and($enrollment->period()->startsOn->format('Y-m-d'))->toBe('2026-09-16')
        ->and($enrollment->isCurrent())->toBeTrue()
        ->and($enrollment->overCapacityReason())->toBe('Hermanos')
        ->and($enrollment->ageException())->toBe($exception)
        ->and($enrollment->registeredBy())->toBe(3);
});

it('is not enrolled from a date after today', function () {
    expect(fn () => Enrollment::enroll(5, 2, sectionA(), onDay('2026-10-16'), onDay('2026-10-15'), null, null, onDay('2026-10-15 08:00'), 3))
        ->toThrow(BusinessRuleViolation::class, 'La fecha de inscripción no puede ser posterior a hoy.');
});

it('a change of section ends this enrollment and opens the next one on the same date', function () {
    $enrollment = enrolled();
    $next = $enrollment->moveTo(Placement::of(11, 'primaria_3', 'primaria'), onDay('2026-10-01'), '  Reorganización  ', onDay('2026-10-15'), null, null, false, onDay('2026-10-15 09:00'), 3);

    expect($enrollment->isCurrent())->toBeFalse()
        ->and($enrollment->period()->endsOn?->format('Y-m-d'))->toBe('2026-10-01')
        ->and($enrollment->period()->endReason)->toBe(EndReason::SectionChange)
        ->and($enrollment->endNote())->toBe('Reorganización')
        ->and($next->id())->toBeNull()
        ->and($next->previousId())->toBe(40)
        ->and($next->placement()->sectionId)->toBe(11)
        ->and($next->period()->startsOn->format('Y-m-d'))->toBe('2026-10-01')
        ->and($next->schoolYearId())->toBe(2)
        ->and($next->studentId())->toBe(5);
});

it('refuses a change without a reason, out of dates or to the same section', function () {
    $other = Placement::of(11, 'primaria_3', 'primaria');

    expect(fn () => enrolled()->moveTo($other, onDay('2026-10-01'), ' ', onDay('2026-10-15'), null, null, false, onDay('2026-10-15'), 3))
        ->toThrow(BusinessRuleViolation::class, 'Indica el motivo del cambio.')
        ->and(fn () => enrolled()->moveTo($other, onDay('2026-09-15'), 'Motivo', onDay('2026-10-15'), null, null, false, onDay('2026-10-15'), 3))
        ->toThrow(BusinessRuleViolation::class, 'La fecha no puede ser anterior al inicio de la inscripción (16/09/2026).')
        ->and(fn () => enrolled()->moveTo($other, onDay('2026-10-16'), 'Motivo', onDay('2026-10-15'), null, null, false, onDay('2026-10-15'), 3))
        ->toThrow(BusinessRuleViolation::class, 'La fecha no puede ser posterior a hoy.')
        ->and(fn () => enrolled()->moveTo(sectionA(), onDay('2026-10-01'), 'Motivo', onDay('2026-10-15'), null, null, false, onDay('2026-10-15'), 3))
        ->toThrow(BusinessRuleViolation::class, 'El estudiante ya está en esa sección.');
});

it('a change of grade needs a directive, and ends as a change of grade', function () {
    $fourth = Placement::of(20, 'primaria_4', 'primaria');

    expect(fn () => enrolled()->moveTo($fourth, onDay('2026-10-01'), 'Ubicación', onDay('2026-10-15'), null, null, false, onDay('2026-10-15'), 3))
        ->toThrow(BusinessRuleViolation::class, 'El cambio de grado lo aprueba un directivo.');

    $enrollment = enrolled();
    $next = $enrollment->moveTo($fourth, onDay('2026-10-01'), 'Ubicación', onDay('2026-10-15'), null, null, true, onDay('2026-10-15'), 9);

    expect($enrollment->period()->endReason)->toBe(EndReason::GradeChange)
        ->and($next->placement()->grade)->toBe('primaria_4')
        ->and($next->registeredBy())->toBe(9);
});

it('a change of section keeps the age exception; a change of grade takes its own', function () {
    $exception = AgeException::of('Repitió', approvedBy: 9, byDirective: true);
    $enrollment = Enrollment::enroll(5, 2, sectionA(), onDay('2026-09-16'), onDay('2026-09-16'), null, $exception, onDay('2026-09-16'), 3);
    $enrollment->identify(EnrollmentId::fromInt(40));
    $next = $enrollment->moveTo(Placement::of(11, 'primaria_3', 'primaria'), onDay('2026-10-01'), 'Motivo', onDay('2026-10-15'), null, null, false, onDay('2026-10-15'), 3);

    expect($next->ageException())->toBe($exception);
});

it('an ended enrollment does not change', function () {
    $enrollment = enrolled();
    $enrollment->end(onDay('2026-10-05'), EndReason::Withdrawal);

    expect(fn () => $enrollment->moveTo(Placement::of(11, 'primaria_3', 'primaria'), onDay('2026-10-06'), 'Motivo', onDay('2026-10-15'), null, null, false, onDay('2026-10-15'), 3))
        ->toThrow(BusinessRuleViolation::class, 'La inscripción ya terminó.')
        ->and(fn () => $enrollment->annul(AnnulmentReason::Duplicate, null, 3, onDay('2026-10-15'), false, false))
        ->toThrow(BusinessRuleViolation::class, 'La inscripción ya terminó.');
});

it('a withdrawal or a transfer ends it on its date, or on its start when it had not started yet', function () {
    $enrollment = enrolled();
    $enrollment->end(onDay('2026-10-05'), EndReason::Withdrawal);

    $future = enrolled('2026-09-16');
    $future->end(onDay('2026-09-01'), EndReason::Transfer);

    expect($enrollment->period()->endsOn?->format('Y-m-d'))->toBe('2026-10-05')
        ->and($enrollment->period()->endReason)->toBe(EndReason::Withdrawal)
        ->and($future->period()->endsOn?->format('Y-m-d'))->toBe('2026-09-16')
        ->and($future->period()->endReason)->toBe(EndReason::Transfer);
});

it('is annulled with a reason, kept and ended on its start', function () {
    $enrollment = enrolled();
    $enrollment->annul(AnnulmentReason::TypingError, null, 3, onDay('2026-09-17 07:59'), false, false);

    expect($enrollment->isCurrent())->toBeFalse()
        ->and($enrollment->period()->endReason)->toBe(EndReason::Annulled)
        ->and($enrollment->period()->endsOn?->format('Y-m-d'))->toBe('2026-09-16')
        ->and($enrollment->annulment()?->reason)->toBe(AnnulmentReason::TypingError)
        ->and($enrollment->annulment()?->annulledBy)->toBe(3)
        ->and($enrollment->annulment()?->annulledAt->format('Y-m-d H:i'))->toBe('2026-09-17 07:59');
});

it('within 48 hours and without grades anyone who manages annuls; after that, or with grades, only a directive', function (string $at, bool $hasGrades, bool $byDirective, bool $allowed) {
    $annul = fn () => enrolled()->annul(AnnulmentReason::Duplicate, null, 3, onDay($at), $byDirective, $hasGrades);

    if ($allowed) {
        expect($annul)->not->toThrow(AnnulmentNotAllowed::class);
    } else {
        expect($annul)->toThrow(AnnulmentNotAllowed::class);
    }
})->with([
    '47 hours' => ['2026-09-18 07:00', false, false, true],
    '48 hours' => ['2026-09-18 08:00', false, false, true],
    '49 hours' => ['2026-09-18 09:00', false, false, false],
    '49 hours, directive' => ['2026-09-18 09:00', false, true, true],
    'with grades' => ['2026-09-16 09:00', true, false, false],
    'with grades, directive' => ['2026-09-16 09:00', true, true, true],
]);

it('says why it can not be annulled', function () {
    expect(fn () => enrolled()->annul(AnnulmentReason::Duplicate, null, 3, onDay('2026-09-20'), false, false))
        ->toThrow(AnnulmentNotAllowed::class, 'Pasaron más de 48 horas: la anula un directivo.')
        ->and(fn () => enrolled()->annul(AnnulmentReason::Duplicate, null, 3, onDay('2026-09-16 09:00'), false, true))
        ->toThrow(AnnulmentNotAllowed::class, 'Ya tiene notas: la anula un directivo.');
});

it('another reason to annul needs a note', function () {
    expect(fn () => enrolled()->annul(AnnulmentReason::Other, '  ', 3, onDay('2026-09-16 09:00'), false, false))
        ->toThrow(BusinessRuleViolation::class, 'Describe el motivo de la anulación.')
        ->and(fn () => enrolled()->annul(null, null, 3, onDay('2026-09-16 09:00'), false, false))
        ->toThrow(BusinessRuleViolation::class, 'Indica el motivo de la anulación.');

    $enrollment = enrolled();
    $enrollment->annul(AnnulmentReason::Other, ' Estudiante equivocado ', 3, onDay('2026-09-16 09:00'), false, false);

    expect($enrollment->annulment()?->note)->toBe('Estudiante equivocado');
});
