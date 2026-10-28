<?php

use AulaX\Evaluation\Domain\EnrollmentPeriod;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\Score;
use AulaX\Evaluation\Domain\ScoredActivity;
use AulaX\Evaluation\Domain\TermResult;
use AulaX\Evaluation\Domain\TermScore;
use AulaX\Evaluation\Domain\Weight;

/**
 * SPEC-011 EVA-R02.7, R02.8, R03.1, R03.2, R03.6, R03.7, R09.3: the grade of
 * a term from the activities that apply to the student.
 */
function scored(string $weight, ?string $grade, bool $applies = true): ScoredActivity
{
    return new ScoredActivity(Weight::of($weight), $applies, GradeMark::fromInput($grade));
}

it('weighs the grades by the share of each activity, NP as 1, rounded half up', function () {
    expect(TermScore::of([scored('30', '9'), scored('30', '10'), scored('40', '10')]))->toEqual(TermResult::scored(10))
        ->and(TermScore::of([scored('50', '9'), scored('50', '10')]))->toEqual(TermResult::scored(10))
        ->and(TermScore::of([scored('50', '9'), scored('50', '9,98')]))->toEqual(TermResult::scored(9))
        ->and(TermScore::of([scored('50', 'NP'), scored('50', '20')]))->toEqual(TermResult::scored(11))
        ->and(TermScore::of([scored('100', '14,5')]))->toEqual(TermResult::scored(15));
});

it('is incomplete while an activity that applies has no grade', function () {
    expect(TermScore::of([scored('50', '15'), scored('50', null)]))->toEqual(TermResult::incomplete());
});

it('shares the 100 % among the activities that apply', function () {
    expect(TermScore::of([scored('20', null, applies: false), scored('40', '10'), scored('40', '20')]))->toEqual(TermResult::scored(15))
        ->and(TermScore::of([scored('70', null, applies: false), scored('30', '13')]))->toEqual(TermResult::scored(13));
});

it('does not apply when no activity applies', function () {
    expect(TermScore::of([scored('100', null, applies: false)]))->toEqual(TermResult::notApplicable())
        ->and(TermScore::of([]))->toEqual(TermResult::notApplicable());
});

it('an activity applies to whoever was enrolled in its section on its date', function () {
    $periods = [
        new EnrollmentPeriod(10, 'media_3', new DateTimeImmutable('2026-09-16'), new DateTimeImmutable('2026-10-15')),
        new EnrollmentPeriod(11, 'media_3', new DateTimeImmutable('2026-10-15'), null),
    ];

    expect(EnrollmentPeriod::applies($periods, 10, new DateTimeImmutable('2026-10-01')))->toBeTrue()
        ->and(EnrollmentPeriod::applies($periods, 10, new DateTimeImmutable('2026-10-15')))->toBeFalse()
        ->and(EnrollmentPeriod::applies($periods, 11, new DateTimeImmutable('2026-10-15')))->toBeTrue()
        ->and(EnrollmentPeriod::applies($periods, 11, new DateTimeImmutable('2026-10-01')))->toBeFalse()
        ->and(EnrollmentPeriod::applies($periods, 12, new DateTimeImmutable('2026-10-20')))->toBeFalse();
});

it('a student who arrived late has NA in the activities before, never NP', function () {
    $periods = [new EnrollmentPeriod(10, 'media_3', new DateTimeImmutable('2026-11-01'), null)];

    expect(EnrollmentPeriod::applies($periods, 10, new DateTimeImmutable('2026-10-01')))->toBeFalse()
        ->and(EnrollmentPeriod::applies($periods, 10, new DateTimeImmutable('2026-11-01')))->toBeTrue();
});

it('after a change of grade, the old one is closed: its sections no longer take new activities', function () {
    $periods = [
        new EnrollmentPeriod(20, 'media_2', new DateTimeImmutable('2026-09-16'), new DateTimeImmutable('2026-11-01')),
        new EnrollmentPeriod(30, 'media_3', new DateTimeImmutable('2026-11-01'), null),
    ];

    expect(EnrollmentPeriod::applies($periods, 20, new DateTimeImmutable('2026-11-05')))->toBeFalse()
        ->and(EnrollmentPeriod::applies($periods, 30, new DateTimeImmutable('2026-11-05')))->toBeTrue()
        ->and(EnrollmentPeriod::currentIn($periods, 20, new DateTimeImmutable('2026-11-05')))->toBeFalse()
        ->and(EnrollmentPeriod::currentIn($periods, 30, new DateTimeImmutable('2026-11-05')))->toBeTrue();
});

it('a score is a score', function () {
    expect(TermResult::scored(15)->score)->toBe(15)
        ->and(TermResult::incomplete()->score)->toBeNull()
        ->and(Score::fromHundredths(1500)->display())->toBe('15');
});
