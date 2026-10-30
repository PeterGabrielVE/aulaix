<?php

use AulaX\Evaluation\Domain\FinalAfterRevision;
use AulaX\Evaluation\Domain\FinalScore;
use AulaX\Evaluation\Domain\GradeMark;
use AulaX\Evaluation\Domain\PendingStanding;
use AulaX\Evaluation\Domain\TermResult;

/**
 * SPEC-016 RVP-R05.2, R05.3: how a pending subject stands in a year:
 * studying until its final grade after revision is decided with the third
 * term closed; then approved or failed.
 */
function pendingResult(int $score, ?string $revision = null): FinalAfterRevision
{
    return FinalAfterRevision::of(
        FinalScore::of([TermResult::scored($score), TermResult::scored($score), TermResult::scored($score)]),
        $revision === null ? null : GradeMark::fromInput($revision),
    );
}

it('stands by the final grade after revision once the third term is closed', function (int $score, ?string $revision, bool $closed, string $standing) {
    expect(PendingStanding::of(pendingResult($score, $revision), $closed))->toBe($standing);
})->with([
    'passed, term open' => [15, null, false, PendingStanding::STUDYING],
    'passed, third term closed' => [15, null, true, PendingStanding::APPROVED],
    'failed, revision missing' => [8, null, true, PendingStanding::STUDYING],
    'failed, revision passed' => [8, '12', true, PendingStanding::APPROVED],
    'failed, revision failed' => [8, '7', true, PendingStanding::FAILED],
]);
