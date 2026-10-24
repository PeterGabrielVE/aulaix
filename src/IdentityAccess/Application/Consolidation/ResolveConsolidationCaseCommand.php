<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

final readonly class ResolveConsolidationCaseCommand
{
    /**
     * @param  list<int>  $accountIds  Every account of the case.
     * @param  ?string  $verificationRef  The support procedure that verified ownership; required to consolidate (IDG-R11.6).
     * @param  bool  $dryRun  Only describe what would change (IDG-R11.4).
     */
    public function __construct(
        public string $operator,
        public array $accountIds,
        public ConsolidationDecision $decision,
        public string $reason,
        public ?string $verificationRef,
        public bool $dryRun,
    ) {}
}
