<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

final readonly class ConsolidationCase
{
    /**
     * @param  list<CaseAccount>  $accounts  Oldest first.
     */
    public function __construct(
        public string $email,
        public array $accounts,
    ) {}
}
