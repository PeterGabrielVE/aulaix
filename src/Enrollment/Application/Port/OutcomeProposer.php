<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

/**
 * An extension point for the module of grades (ADR 0012): SPEC-011 proposes
 * the result of the year of each student from its grades (EVA-R08). The
 * manager confirms it or records another allowed one (MAT-R06). Until a
 * module registers one, there is no proposal.
 */
interface OutcomeProposer
{
    /**
     * The container tag a module puts its proposer under.
     */
    public const string TAG = 'enrollment.outcome_proposers';

    /**
     * @param  array<int, int>  $students  Enrollment id => student id, of the section.
     * @return array<int, OutcomeSuggestion> Enrollment id => proposal.
     */
    public function propose(int $schoolYearId, int $sectionId, array $students): array;
}
