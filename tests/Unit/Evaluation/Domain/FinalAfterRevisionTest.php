<?php

use AulaX\Evaluation\Domain\FinalAfterRevision;
use AulaX\Evaluation\Domain\FinalScore;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\TermResult;

/**
 * SPEC-016 RVP-R02.1, R02.2, NF03: the final grade that counts for the
 * year, once its revision is in.
 */
function finalScoreOf(?int $score): FinalScore
{
    return $score === null
        ? FinalScore::of([TermResult::scored(15), TermResult::incomplete(), TermResult::scored(15)])
        : FinalScore::of([TermResult::scored($score), TermResult::scored($score), TermResult::scored($score)]);
}

it('decides the final grade with its revision', function (?int $final, ?string $revision, string $state, ?int $score) {
    $result = FinalAfterRevision::of(finalScoreOf($final), $revision === null ? null : GradeMark::fromInput($revision));

    expect([$result->state, $result->score])->toBe([$state, $score]);
})->with([
    'passed, no revision needed' => [15, null, 'approved', 15],
    'failed, revision missing' => [8, null, 'needs_revision', null],
    'revision of 10 passes, with its grade' => [8, '10', 'approved', 10],
    'revision of 15,5 rounds to 16' => [8, '15,5', 'approved', 16],
    'revision of 9,5 rounds to 10 and passes' => [8, '9,5', 'approved', 10],
    'revision of 9,49 fails, the final stays' => [8, '9,49', 'failed', 8],
    'NP in the revision fails' => [8, 'NP', 'failed', 8],
    'incomplete final' => [null, null, 'incomplete', null],
]);

it('tells whether the subject passed or failed in the end', function () {
    expect(FinalAfterRevision::of(finalScoreOf(8), GradeMark::fromInput('12'))->passed())->toBeTrue()
        ->and(FinalAfterRevision::of(finalScoreOf(8), GradeMark::notPresented())->failed())->toBeTrue()
        ->and(FinalAfterRevision::of(finalScoreOf(8), null)->failed())->toBeFalse()
        ->and(FinalAfterRevision::of(finalScoreOf(8), null)->isDecided())->toBeFalse();
});
