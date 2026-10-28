<?php

use AulaX\Enrollment\Domain\EndReason;
use AulaX\Enrollment\Domain\Period;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-010 MAT-R04.2: an enrollment lasts from a date until it ends, for a
 * reason.
 */
it('is current until it ends, and ends for a reason', function () {
    $period = Period::from(new DateTimeImmutable('2026-09-16'));
    $ended = $period->endedOn(new DateTimeImmutable('2026-11-02'), EndReason::SectionChange);

    expect($period->isCurrent())->toBeTrue()
        ->and($period->endsOn)->toBeNull()
        ->and($period->endReason)->toBeNull()
        ->and($ended->isCurrent())->toBeFalse()
        ->and($ended->startsOn->format('Y-m-d'))->toBe('2026-09-16')
        ->and($ended->endsOn?->format('Y-m-d'))->toBe('2026-11-02')
        ->and($ended->endReason)->toBe(EndReason::SectionChange);
});

it('does not end before it starts', function () {
    $period = Period::from(new DateTimeImmutable('2026-09-16'));

    expect(fn () => $period->endedOn(new DateTimeImmutable('2026-09-15'), EndReason::Withdrawal))
        ->toThrow(BusinessRuleViolation::class, 'La fecha no puede ser anterior al inicio de la inscripción (16/09/2026).')
        ->and($period->endedOn(new DateTimeImmutable('2026-09-16'), EndReason::Annulled)->endsOn?->format('Y-m-d'))->toBe('2026-09-16');
});

it('has a reason to end if and only if it ended', function () {
    expect(fn () => Period::reconstitute(new DateTimeImmutable('2026-09-16'), new DateTimeImmutable('2026-10-01'), null))
        ->toThrow(LogicException::class)
        ->and(fn () => Period::reconstitute(new DateTimeImmutable('2026-09-16'), null, EndReason::Transfer))
        ->toThrow(LogicException::class);
});

it('does not end twice', function () {
    $ended = Period::from(new DateTimeImmutable('2026-09-16'))->endedOn(new DateTimeImmutable('2026-10-01'), EndReason::Withdrawal);

    expect(fn () => $ended->endedOn(new DateTimeImmutable('2026-10-02'), EndReason::Transfer))->toThrow(LogicException::class);
});
