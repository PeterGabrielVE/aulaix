<?php

use AulaX\Evaluation\Domain\FinalAfterRevision;
use AulaX\Evaluation\Domain\FinalScore;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\Literal;
use AulaX\Evaluation\Domain\OutcomeProposal;
use AulaX\Evaluation\Domain\TermResult;

/**
 * SPEC-011 EVA-R08.1 – R08.3, R08.5 and SPEC-016 RVP-R02.3, R02.4: the
 * result of the year proposed from the final grades after their revision
 * of each level, or what is missing to propose it.
 */
function finalOf(int $score, ?string $revision = 'NP'): FinalAfterRevision
{
    $final = FinalScore::of([TermResult::scored($score), TermResult::scored($score), TermResult::scored($score)]);

    // A failed final counts once its revision, NP unless said, is in.
    return FinalAfterRevision::of($final, $final->failed() && $revision !== null ? GradeMark::fromInput($revision) : null);
}

it('proposes in Media General by the failed final grades', function () {
    expect(OutcomeProposal::forSecondary([4 => finalOf(15), 5 => finalOf(12)], []))->toMatchArray(['outcome' => 'promoted', 'pendingSubjects' => [], 'missing' => []])
        ->and(OutcomeProposal::forSecondary([4 => finalOf(9), 5 => finalOf(12), 6 => finalOf(8)], [])['outcome'])->toBe('promoted_with_pending')
        ->and(OutcomeProposal::forSecondary([4 => finalOf(9), 5 => finalOf(12), 6 => finalOf(8)], [])['pendingSubjects'])->toBe([4, 6])
        ->and(OutcomeProposal::forSecondary([4 => finalOf(9), 5 => finalOf(7), 6 => finalOf(8)], [])['outcome'])->toBe('repeats');
});

it('proposes nothing in Media General while a final grade is missing, saying which', function () {
    $incomplete = FinalScore::of([TermResult::scored(15), TermResult::incomplete(), TermResult::scored(15)]);
    $none = FinalScore::of([TermResult::notApplicable(), TermResult::notApplicable(), TermResult::notApplicable()]);

    expect(OutcomeProposal::forSecondary([4 => finalOf(15), 5 => FinalAfterRevision::of($incomplete, null), 6 => FinalAfterRevision::of($none, null)], [5 => 'Física', 6 => 'Química']))
        ->toBe(['outcome' => null, 'pendingSubjects' => [], 'missing' => ['Falta la definitiva de Física.', 'Falta la definitiva de Química.']]);
});

it('proposes in Primaria by the final literal, and always promotes in Inicial', function () {
    expect(OutcomeProposal::forPrimary(Literal::D)['outcome'])->toBe('promoted')
        ->and(OutcomeProposal::forPrimary(Literal::E)['outcome'])->toBe('repeats')
        ->and(OutcomeProposal::forPrimary(null))->toBe(['outcome' => null, 'pendingSubjects' => [], 'missing' => ['Falta el literal final.']])
        ->and(OutcomeProposal::forInitial()['outcome'])->toBe('promoted');
});

it('counts a subject passed in its revision, and waits for the revisions still missing', function () {
    expect(OutcomeProposal::forSecondary([4 => finalOf(9, '12'), 5 => finalOf(12), 6 => finalOf(8)], [])['pendingSubjects'])->toBe([6])
        ->and(OutcomeProposal::forSecondary([4 => finalOf(9, null), 5 => finalOf(12)], [4 => 'Matemática']))
        ->toBe(['outcome' => null, 'pendingSubjects' => [], 'missing' => ['Falta la revisión de Matemática.']]);
});
