<?php

use AulaX\Audit\Application\FieldChange;
use AulaX\Enrollment\Application\EnrollmentAudit;
use AulaX\Enrollment\Domain\AgeException;
use AulaX\Enrollment\Domain\AnnulmentReason;
use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\Enrollment;
use AulaX\Enrollment\Domain\EnrollmentId;
use AulaX\Enrollment\Domain\Placement;

/**
 * SPEC-010 MAT-R12.1, R12.2: the audit log entries of enrollments, with the
 * reasons and who approved, never health data.
 */
function auditedEnrollment(?string $overCapacity = 'Hermanos', ?AgeException $exception = null): Enrollment
{
    $enrollment = Enrollment::enroll(5, 2, Placement::of(10, 'primaria_3', 'primaria'), new DateTimeImmutable('2026-09-16'), new DateTimeImmutable('2026-09-16'), $overCapacity, $exception, new DateTimeImmutable('2026-09-16 08:00'), 3);
    $enrollment->identify(EnrollmentId::fromInt(40));

    return $enrollment;
}

/**
 * @param  list<FieldChange>  $changes
 * @return array<string, mixed>
 */
function auditedValues(array $changes): array
{
    $values = [];

    foreach ($changes as $change) {
        $values[$change->field] = $change->to;
    }

    return $values;
}

it('records an enrollment with its over capacity reason and its age exception', function () {
    $event = EnrollmentAudit::enrolled(auditedEnrollment(exception: AgeException::of('Repitió', approvedBy: 9, byDirective: true)));

    expect($event->type)->toBe('enrollment.enrollment.enrolled')
        ->and($event->subject->type)->toBe('enrollment')
        ->and($event->subject->id)->toBe('40')
        ->and(auditedValues($event->changes))->toBe([
            'student_id' => 5,
            'school_year_id' => 2,
            'section_id' => 10,
            'grade' => 'primaria_3',
            'starts_on' => '2026-09-16',
            'over_capacity_reason' => 'Hermanos',
            'age_exception' => 'Repitió',
            'age_exception_by' => 9,
        ]);
});

it('records a change of section or grade with its date, reason and who approved it', function () {
    $enrollment = auditedEnrollment(null);
    $next = $enrollment->moveTo(Placement::of(20, 'primaria_4', 'primaria'), new DateTimeImmutable('2026-10-01'), 'Ubicación', new DateTimeImmutable('2026-10-15'), null, null, true, new DateTimeImmutable('2026-10-15'), 9);
    $next->identify(EnrollmentId::fromInt(41));
    $event = EnrollmentAudit::moved($enrollment, $next);

    expect($event->type)->toBe('enrollment.enrollment.grade_changed')
        ->and($event->subject->id)->toBe('41')
        ->and(auditedValues($event->changes))->toBe([
            'section_id' => 20,
            'grade' => 'primaria_4',
            'date' => '2026-10-01',
            'reason' => 'Ubicación',
            'approved_by' => 9,
        ]);
});

it('records the end of an enrollment by a withdrawal or a transfer, and an annulment with its reason', function () {
    $ended = auditedEnrollment(null);
    $ended->end(new DateTimeImmutable('2026-10-05'), EndReason::Transfer);
    $annulled = auditedEnrollment(null);
    $annulled->annul(AnnulmentReason::Other, 'Estudiante equivocado', 3, new DateTimeImmutable('2026-09-16 09:00'), false, false);

    expect(EnrollmentAudit::ended($ended)->type)->toBe('enrollment.enrollment.ended')
        ->and(auditedValues(EnrollmentAudit::ended($ended)->changes))->toBe(['ends_on' => '2026-10-05', 'end_reason' => 'transfer'])
        ->and(EnrollmentAudit::annulled($annulled)->type)->toBe('enrollment.enrollment.annulled')
        ->and(auditedValues(EnrollmentAudit::annulled($annulled)->changes))->toBe(['reason' => 'other', 'note' => 'Estudiante equivocado']);
});

it('never records health data', function () {
    $fields = array_map(fn (FieldChange $change) => $change->field, EnrollmentAudit::enrolled(auditedEnrollment())->changes);

    expect(array_intersect($fields, ['allergies', 'medical_conditions', 'disability', 'health']))->toBe([]);
});
