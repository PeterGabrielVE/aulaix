<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

/**
 * Read side of the support console: the emails whose accounts are marked
 * for review (IDG-R11.1).
 */
interface ConsolidationCases
{
    /**
     * @return list<ConsolidationCase> By email.
     */
    public function underReview(): array;
}
