<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

/**
 * What a resolution did, or would do on a dry run.
 */
final readonly class CaseResolution
{
    /**
     * @param  ?int  $survivorId  The account kept when consolidating.
     * @param  list<int>  $affectedIds  Merged away, or kept separate.
     */
    public function __construct(
        public ConsolidationDecision $decision,
        public ?int $survivorId,
        public array $affectedIds,
        public bool $applied,
    ) {}
}
