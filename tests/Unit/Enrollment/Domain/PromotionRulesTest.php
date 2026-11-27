<?php

use AulaX\Enrollment\Domain\Level;
use AulaX\Enrollment\Domain\Outcome;
use AulaX\Enrollment\Domain\PromotionResult;
use AulaX\Enrollment\Domain\PromotionRules;
use AulaX\Enrollment\Domain\ResultNotAllowed;

/**
 * SPEC-010 MAT-R06.3 – R06.7, R07.1, R15.1, R15.2: what result each level
 * allows, the grade it leads to, and who graduates.
 */
const GRADE_ORDER = [
    'maternal_1', 'maternal_2', 'maternal_3', 'preescolar_1', 'preescolar_2', 'preescolar_3',
    'primaria_1', 'primaria_2', 'primaria_3', 'primaria_4', 'primaria_5', 'primaria_6',
    'media_1', 'media_2', 'media_3', 'media_4', 'media_5',
];

it('in Educación Inicial a student is always promoted', function (Outcome $outcome) {
    expect(fn () => PromotionRules::check(Level::Initial, PromotionResult::of($outcome, []), []))
        ->toThrow(ResultNotAllowed::class, 'En Educación Inicial el estudiante siempre es promovido.');
})->with([Outcome::Repeats, Outcome::LostByAbsence]);

it('in Primaria a student is promoted, repeats or lost the year by absence, with no pending subjects', function () {
    PromotionRules::check(Level::Primary, PromotionResult::of(Outcome::Repeats, []), []);
    PromotionRules::check(Level::Initial, PromotionResult::of(Outcome::Promoted, []), []);

    expect(fn () => PromotionRules::check(Level::Primary, PromotionResult::of(Outcome::PromotedWithPending, [4]), [4]))
        ->toThrow(ResultNotAllowed::class, 'En Primaria no hay materias pendientes: el estudiante es promovido o repite.');
});

it('in Media General a promotion with pending subjects needs one or two of them', function () {
    expect(fn () => PromotionResult::of(Outcome::PromotedWithPending, []))
        ->toThrow(ResultNotAllowed::class, 'Indica las materias pendientes: una o dos.')
        ->and(fn () => PromotionResult::of(Outcome::PromotedWithPending, [4, 5, 6]))
        ->toThrow(ResultNotAllowed::class, 'Con 3 o más materias reprobadas el estudiante repite.')
        ->and(fn () => PromotionResult::of(Outcome::Promoted, [4]))
        ->toThrow(ResultNotAllowed::class, 'Solo un estudiante promovido con materia pendiente tiene materias pendientes.')
        ->and(PromotionResult::of(Outcome::PromotedWithPending, [5, 4, 5])->pendingSubjects)->toBe([4, 5]);

    PromotionRules::check(Level::Secondary, PromotionResult::of(Outcome::PromotedWithPending, [4, 5]), [4, 5, 6]);
});

it('pending subjects are of the curriculum of the grade', function () {
    expect(fn () => PromotionRules::check(Level::Secondary, PromotionResult::of(Outcome::PromotedWithPending, [9]), [4, 5]))
        ->toThrow(ResultNotAllowed::class, 'Las materias pendientes deben ser del pensum del grado.');
});

it('leads to the next grade when promoted, and to the same one when it repeats or lost the year', function () {
    expect(PromotionRules::nextGrade('preescolar_3', Outcome::Promoted, GRADE_ORDER))->toBe('primaria_1')
        ->and(PromotionRules::nextGrade('media_2', Outcome::PromotedWithPending, GRADE_ORDER))->toBe('media_3')
        ->and(PromotionRules::nextGrade('primaria_4', Outcome::Repeats, GRADE_ORDER))->toBe('primaria_4')
        ->and(PromotionRules::nextGrade('primaria_4', Outcome::LostByAbsence, GRADE_ORDER))->toBe('primaria_4')
        ->and(PromotionRules::nextGrade('media_5', Outcome::Promoted, GRADE_ORDER))->toBeNull();
});

it('promoted from 5.º año with no pending subjects graduates; with pending ones, waits to graduate', function () {
    expect(PromotionRules::graduates('media_5', PromotionResult::of(Outcome::Promoted, [])))->toBeTrue()
        ->and(PromotionRules::waitsToGraduate('media_5', PromotionResult::of(Outcome::PromotedWithPending, [4])))->toBeTrue()
        ->and(PromotionRules::graduates('media_5', PromotionResult::of(Outcome::PromotedWithPending, [4])))->toBeFalse()
        ->and(PromotionRules::graduates('media_4', PromotionResult::of(Outcome::Promoted, [])))->toBeFalse()
        ->and(PromotionRules::waitsToGraduate('media_4', PromotionResult::of(Outcome::PromotedWithPending, [4])))->toBeFalse()
        ->and(PromotionRules::graduates('media_5', PromotionResult::of(Outcome::Repeats, [])))->toBeFalse();
});

it('offers the results each level allows', function () {
    expect(PromotionRules::allowedOutcomes(Level::Initial))->toBe([Outcome::Promoted])
        ->and(PromotionRules::allowedOutcomes(Level::Primary))->toBe([Outcome::Promoted, Outcome::Repeats, Outcome::LostByAbsence])
        ->and(PromotionRules::allowedOutcomes(Level::Secondary))->toBe([Outcome::Promoted, Outcome::PromotedWithPending, Outcome::Repeats, Outcome::LostByAbsence]);
});
