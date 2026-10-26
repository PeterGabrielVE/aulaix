<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Infrastructure;

use AulaX\IdentityAccess\Application\Consolidation\ConsolidationOperators;
use AulaX\Platform\Application\Operators\ActiveOperatorQuery;

/**
 * The panel's active operators, by email (SPEC-014 PLT-R06.6, V3): they
 * replace the environment's list (IDENTITY_CONSOLIDATION_OPERATORS).
 */
final readonly class PlatformConsolidationOperators implements ConsolidationOperators
{
    public function __construct(private ActiveOperatorQuery $activeOperator) {}

    public function isAuthorized(string $operator): bool
    {
        return ($this->activeOperator)($operator);
    }
}
