<?php

use AulaX\Evaluation\Domain\ActivityGrades;
use AulaX\Evaluation\Domain\ActivityId;
use AulaX\Evaluation\Domain\EnrollmentPeriod;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\GradeNotValid;
use AulaX\Evaluation\Domain\GroupAudience;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\SectionAudience;

/**
 * SPEC-016 (ADR 0013): to whom an activity applies depends on the owner of
 * its plan: in a section, the enrollment periods (NA included); in a group
 * of pending subjects, every member, always.
 */
it('in a section, applies to whoever was enrolled in it on the date, and takes whoever is in it today', function () {
    $audience = new SectionAudience([
        1 => [new EnrollmentPeriod(10, 'media_3', new DateTimeImmutable('2026-09-16'), null)],
        2 => [new EnrollmentPeriod(10, 'media_3', new DateTimeImmutable('2026-10-15'), null)],
    ], 10);

    expect($audience->applies(1, new DateTimeImmutable('2026-10-05')))->toBeTrue()
        ->and($audience->applies(2, new DateTimeImmutable('2026-10-05')))->toBeFalse()
        ->and($audience->isCurrent(2, new DateTimeImmutable('2026-10-20')))->toBeTrue()
        ->and($audience->isCurrent(3, new DateTimeImmutable('2026-10-20')))->toBeFalse();
});

it('in a group of pending subjects, applies to every member, whatever the date, and to nobody else', function () {
    $audience = new GroupAudience([1, 2]);

    expect($audience->applies(2, new DateTimeImmutable('2026-09-20')))->toBeTrue()
        ->and($audience->isCurrent(1, new DateTimeImmutable('2027-06-01')))->toBeTrue()
        ->and($audience->isCurrent(3, new DateTimeImmutable('2026-10-20')))->toBeFalse();
});

it('records the grades of a group with its audience, refusing who is not a member', function () {
    $grades = ActivityGrades::of(ActivityId::fromInt(7), PlanOwner::pendingGroup(3), new DateTimeImmutable('2026-10-05'), []);

    $grades->record([1 => GradeMark::fromInput('12')], new GroupAudience([1]), new DateTimeImmutable('2026-10-20'));

    expect($grades->markOf(1)?->value())->toBe(1200)
        ->and($grades->sectionId())->toBeNull()
        ->and(fn () => $grades->record([4 => GradeMark::fromInput('10')], new GroupAudience([1]), new DateTimeImmutable('2026-10-20')))
        ->toThrow(GradeNotValid::class, 'El estudiante no está en el grupo de pendientes.');
});
