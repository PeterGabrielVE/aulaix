<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application;

use AulaX\Enrollment\Application\Port\OutcomeProposer;
use AulaX\Enrollment\Application\Port\OutcomeSuggestion;

/**
 * Every registered OutcomeProposer, asked together; the first proposal of
 * an enrollment wins.
 */
final readonly class OutcomeProposers
{
    /**
     * @param  iterable<OutcomeProposer>  $proposers
     */
    public function __construct(private iterable $proposers) {}

    /**
     * @param  array<int, int>  $students  Enrollment id => student id.
     * @return array<int, OutcomeSuggestion>
     */
    public function propose(int $schoolYearId, int $sectionId, array $students): array
    {
        $proposals = [];

        foreach ($this->proposers as $proposer) {
            $proposals += $proposer->propose($schoolYearId, $sectionId, $students);
        }

        return $proposals;
    }
}
