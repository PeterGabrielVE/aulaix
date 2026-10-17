<?php

declare(strict_types=1);

namespace AulaX\IdentityAccess\Application\Port;

use AulaX\IdentityAccess\Domain\Account\AccountId;
use AulaX\Tenancy\Application\TenantContext;

/**
 * Runs an operation in each institution the account has a membership in
 * (any status, inactive institutions included), with that institution
 * active: memberships, roles and the institution's audit log are written
 * there (ADR 0006 C). Leaves no institution active.
 */
interface InstitutionScope
{
    /**
     * @param  callable(TenantContext): void  $operation
     */
    public function forEachInstitutionOf(AccountId $account, callable $operation): void;
}
