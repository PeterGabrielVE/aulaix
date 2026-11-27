<?php

declare(strict_types=1);

namespace AulaX\Students\Application\Port;

/**
 * What the person acting may do with the students of the open institution:
 * the fine check of S-04, behind the permission middleware of the route
 * (EST-NF01).
 */
interface StudentPermissions
{
    /**
     * Holds gestionar-estudiantes (EST-R09.1).
     */
    public function canManage(): bool;

    /**
     * Holds ver-estudiantes (EST-R09.3).
     */
    public function canView(): bool;
}
