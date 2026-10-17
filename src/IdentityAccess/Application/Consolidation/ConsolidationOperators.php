<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

/**
 * The support staff allowed to resolve consolidation cases, configured per
 * environment (IDG-R11.3).
 */
interface ConsolidationOperators
{
    public function isAuthorized(string $operator): bool;
}
