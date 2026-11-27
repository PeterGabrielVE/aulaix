<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Application\Port;

/**
 * What the person acting may do with the open institution: the fine check
 * of S-04, behind the permission middleware of the route (INS-NF01).
 */
interface InstitutionPermissions
{
    /**
     * Holds `gestionar-plantel` in the open institution (INS-R05.1).
     */
    public function canManage(): bool;
}
