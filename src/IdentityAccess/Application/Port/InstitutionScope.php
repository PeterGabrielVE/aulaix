<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\Tenancy\Application\TenantContext;

/**
 * Runs an operation in each institution the account has a membership in
 * (any status, inactive institutions included), with that institution
 * active: memberships, roles and the institution's audit log are written
 * there (ADR 0006 C). Leaves no institution active. Takes the account's id
 * as a plain int, so other modules' deletion blockers can use it (A-06).
 */
interface InstitutionScope
{
    /**
     * @param  callable(TenantContext): void  $operation
     */
    public function forEachInstitutionOf(int $accountId, callable $operation): void;
}
