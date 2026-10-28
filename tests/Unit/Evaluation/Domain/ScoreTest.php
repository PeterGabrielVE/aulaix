<?php

use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\GradeNotValid;
use AulaX\Evaluation\Domain\Score;
use AulaX\Evaluation\Domain\Weight;

/**
 * SPEC-011 EVA-R02.2, R02.3, NF03: a grade from 1 to 20 with up to two
 * decimals, kept in hundredths; "no presentó" counts as 1; and the weight of
 * an activity.
 */
it('takes a grade from 1 to 20 with up to two decimals, comma or point', function (string|int|float $input, int $hundredths) {
    expect(Score::of($input)->hundredths)->toBe($hundredths);
})->with([
    'one' => ['1', 100],
    'twenty' => [20, 2000],
    'comma' => ['9,5', 950],
    'point' => ['15.75', 1575],
    'spaces' => [' 12 ', 1200],
    'two decimals' => ['19,99', 1999],
]);

it('refuses a grade out of range, with more decimals or not a number', function (string $input) {
    expect(fn () => Score::of($input))->toThrow(GradeNotValid::class, 'La nota debe estar entre 1 y 20, con hasta dos decimales.');
})->with(['0', '0,99', '20,01', '9,999', 'diez', '', '-5', '1e1']);

it('shows a grade with a comma and its decimals only when it has them', function () {
    expect(Score::of('9,5')->display())->toBe('9,5')
        ->and(Score::of('9,25')->display())->toBe('9,25')
        ->and(Score::of('12')->display())->toBe('12');
});

it('marks a grade, an NP worth 1 in the arithmetic, or an incomplete one', function () {
    expect(GradeMark::score(Score::of('14'))->value())->toBe(1400)
        ->and(GradeMark::notPresented()->value())->toBe(100)
        ->and(GradeMark::notPresented()->isNotPresented())->toBeTrue()
        ->and(GradeMark::fromInput('NP')->isNotPresented())->toBeTrue()
        ->and(GradeMark::fromInput('np')->isNotPresented())->toBeTrue()
        ->and(GradeMark::fromInput('9,5')->value())->toBe(950)
        ->and(GradeMark::fromInput(null))->toBeNull()
        ->and(GradeMark::fromInput(''))->toBeNull();
});

it('takes the weight of an activity as a percentage with up to two decimals', function () {
    expect(Weight::of('25')->hundredths)->toBe(2500)
        ->and(Weight::of('33,33')->hundredths)->toBe(3333)
        ->and(Weight::of(100)->hundredths)->toBe(10000)
        ->and(fn () => Weight::of('0'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Weight::of('100,01'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Weight::of('10,555'))->toThrow(InvalidArgumentException::class);
});
