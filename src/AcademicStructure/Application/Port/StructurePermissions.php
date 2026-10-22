<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application\Port;

/**
 * What the person acting may do with the structure of the open institution:
 * the fine check of S-04, behind the permission middleware of the route
 * (EAC-NF01).
 */
interface StructurePermissions
{
    /**
     * Holds gestionar-estructura (EAC-R10.1).
     */
    public function canManage(): bool;

    /**
     * Holds ver-estructura (EAC-R08.1).
     */
    public function canView(): bool;
}
