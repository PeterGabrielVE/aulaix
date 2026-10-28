<?php

use AulaX\Evaluation\Domain\FinalScore;
use AulaX\Evaluation\Domain\TermResult;

/**
 * SPEC-011 EVA-R03.3 – R03.5, R03.7: the final grade of a subject, the
 * average of its terms.
 */
it('averages the three terms, rounded half up', function () {
    expect(FinalScore::of([TermResult::scored(10), TermResult::scored(10), TermResult::scored(9)])->score)->toBe(10)
        ->and(FinalScore::of([TermResult::scored(9), TermResult::scored(9), TermResult::scored(10)])->score)->toBe(9)
        ->and(FinalScore::of([TermResult::scored(15), TermResult::scored(16), TermResult::scored(16)])->score)->toBe(16);
});

it('is incomplete while a term is', function () {
    $final = FinalScore::of([TermResult::scored(15), TermResult::incomplete(), TermResult::scored(16)]);

    expect($final->isComplete())->toBeFalse()
        ->and($final->score)->toBeNull()
        ->and($final->failed())->toBeFalse();
});

it('leaves out a term that does not apply', function () {
    expect(FinalScore::of([TermResult::notApplicable(), TermResult::scored(14), TermResult::scored(15)])->score)->toBe(15);
});

it('fails below 10', function () {
    expect(FinalScore::of([TermResult::scored(9), TermResult::scored(9), TermResult::scored(10)])->failed())->toBeTrue()
        ->and(FinalScore::of([TermResult::scored(10), TermResult::scored(10), TermResult::scored(9)])->failed())->toBeFalse();
});
