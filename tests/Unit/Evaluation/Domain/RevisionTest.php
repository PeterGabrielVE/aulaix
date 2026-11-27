<?php

use AulaX\Evaluation\Domain\FinalScore;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\PlanOwner;
use AulaX\Evaluation\Domain\Revision;
use AulaX\Evaluation\Domain\RevisionNotAllowed;
use AulaX\Evaluation\Domain\TermResult;

/**
 * SPEC-016 RVP-R01.2 – R01.5, R01.7: the revision of a failed final grade,
 * once the third term is closed and before the result of the year.
 */
function revisionOf(?GradeMark $mark = null): Revision
{
    return Revision::of(null, 2, 4, 1, PlanOwner::section(10), $mark);
}

function finalOfScore(int $score): FinalScore
{
    return FinalScore::of([TermResult::scored($score), TermResult::scored($score), TermResult::scored($score)]);
}

it('records the revision of a failed final, and tells what changed', function () {
    $revision = revisionOf();

    expect($revision->record(GradeMark::fromInput('12,5'), finalOfScore(8), true, false)?->after)->toBe('12,5')
        ->and($revision->mark()?->value())->toBe(1250)
        ->and($revision->record(GradeMark::fromInput('12,50'), finalOfScore(8), true, false))->toBeNull()
        ->and($revision->record(GradeMark::notPresented(), finalOfScore(8), true, false)?->before)->toBe('12,5')
        ->and($revision->record(null, finalOfScore(8), true, false)?->after)->toBeNull()
        ->and($revision->mark())->toBeNull();
});

it('refuses a revision of a final that is not failed', function () {
    $incomplete = FinalScore::of([TermResult::scored(15), TermResult::incomplete(), TermResult::scored(15)]);

    expect(fn () => revisionOf()->record(GradeMark::fromInput('12'), finalOfScore(15), true, false))
        ->toThrow(RevisionNotAllowed::class, 'La definitiva no está reprobada: no lleva revisión.')
        ->and(fn () => revisionOf()->record(GradeMark::fromInput('12'), $incomplete, true, false))->toThrow(RevisionNotAllowed::class);
});

it('refuses a revision before the third term is closed, or once the result of the year is recorded', function () {
    expect(fn () => revisionOf()->record(GradeMark::fromInput('12'), finalOfScore(8), false, false))
        ->toThrow(RevisionNotAllowed::class, 'La revisión se registra con el tercer lapso cerrado.')
        ->and(fn () => revisionOf()->record(GradeMark::fromInput('12'), finalOfScore(8), true, true))
        ->toThrow(RevisionNotAllowed::class, 'El estudiante ya tiene su resultado del año: corrígelo primero en Prosecución.');
});
