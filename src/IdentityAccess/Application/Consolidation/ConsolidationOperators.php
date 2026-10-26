<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Consolidation;

/**
 * Who may resolve consolidation cases (IDG-R11.3): since SPEC-014, the
 * active operators of the platform panel, by email.
 */
interface ConsolidationOperators
{
    public function isAuthorized(string $operator): bool;
}
