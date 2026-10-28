<?php

use AulaX\Enrollment\Domain\AgeCheck;
use AulaX\Enrollment\Domain\AgeException;
use AulaX\Enrollment\Domain\AgeFit;
use AulaX\Enrollment\Domain\AgeOutOfRange;
use AulaX\Enrollment\Domain\AgeRule;
use AulaX\Shared\Domain\BusinessRuleViolation;

/**
 * SPEC-010 MAT-R03.1 – R03.5, R03.7: the age of a student at the cutoff
 * date, against the regular age and the allowed range of the grade.
 */
function firstGrade(): AgeRule
{
    return new AgeRule('primaria_1', regular: 6, min: 5, max: 7);
}

function ageCheck(string $birthDate, ?AgeRule $rule = null, string $schoolYearStart = '2026-09-16', string $cutoff = '12-31'): AgeCheck
{
    return AgeCheck::of(new DateTimeImmutable($birthDate), new DateTimeImmutable($schoolYearStart), $cutoff, $rule ?? firstGrade());
}

it('takes the age on December 31 of the year the school year starts', function () {
    expect(ageCheck('2020-12-31')->age)->toBe(6)
        ->and(ageCheck('2021-01-01')->age)->toBe(5)
        ->and(ageCheck('2020-06-01', schoolYearStart: '2027-09-15')->age)->toBe(7)
        ->and(ageCheck('2020-06-30', cutoff: '06-30')->age)->toBe(6);
});

it('counts a birthday on February 29', function () {
    expect(ageCheck('2020-02-29')->age)->toBe(6)
        ->and(ageCheck('2020-02-29', cutoff: '02-28', schoolYearStart: '2027-01-10')->age)->toBe(6);
});

it('is regular at the regular age, a warning inside the range, and out of range beyond it', function (string $birthDate, AgeFit $fit) {
    expect(ageCheck($birthDate)->fit)->toBe($fit);
})->with([
    'regular' => ['2020-03-10', AgeFit::Regular],
    'youngest of the range' => ['2021-03-10', AgeFit::Warning],
    'oldest of the range' => ['2019-03-10', AgeFit::Warning],
    'too young' => ['2022-03-10', AgeFit::OutOfRange],
    'too old' => ['2018-03-10', AgeFit::OutOfRange],
]);

it('does not check a grade with no ages', function () {
    $check = AgeCheck::of(new DateTimeImmutable('2024-01-01'), new DateTimeImmutable('2026-09-16'), '12-31', null);

    expect($check->fit)->toBe(AgeFit::Unchecked)
        ->and($check->admit(null))->toBeNull();
});

it('admits inside the range with no exception, and out of it only with one', function () {
    expect(ageCheck('2021-03-10')->admit(null))->toBeNull()
        ->and(fn () => ageCheck('2022-03-10')->admit(null))
        ->toThrow(AgeOutOfRange::class, 'Tiene 4 años y el rango del grado es de 5 a 7 años: hace falta la excepción de un directivo.');

    $exception = AgeException::of('Adelantado por evaluación psicopedagógica', approvedBy: 9, byDirective: true);

    expect(ageCheck('2022-03-10')->admit($exception))->toBe($exception)
        ->and(ageCheck('2020-03-10')->admit($exception))->toBeNull();
});

it('takes an exception only from a directive, with a justification', function () {
    expect(fn () => AgeException::of('Adelantado', approvedBy: 9, byDirective: false))
        ->toThrow(BusinessRuleViolation::class, 'Solo un directivo puede aprobar una inscripción fuera del rango de edad.')
        ->and(fn () => AgeException::of('   ', approvedBy: 9, byDirective: true))
        ->toThrow(BusinessRuleViolation::class, 'Indica la justificación de la excepción de edad.');

    $exception = AgeException::of('  Adelantado  ', approvedBy: 9, byDirective: true);

    expect($exception->justification)->toBe('Adelantado')
        ->and($exception->approvedBy)->toBe(9);
});

it('keeps the ages of a grade in order', function () {
    expect(fn () => new AgeRule('primaria_1', regular: 8, min: 5, max: 7))->toThrow(InvalidArgumentException::class);
});
