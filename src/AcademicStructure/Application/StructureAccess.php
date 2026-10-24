<?php

declare(strict_types=1);

namespace AulaX\AcademicStructure\Application;

use AulaX\AcademicStructure\Application\Port\StructurePermissions;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The fine permission check of every use case (S-04, EAC-NF01).
 */
final class StructureAccess
{
    /**
     * @throws AuthorizationDenied
     */
    public static function ensureCanManage(StructurePermissions $permissions): void
    {
        if (! $permissions->canManage()) {
            throw new AuthorizationDenied('No tienes permiso para gestionar la estructura.');
        }
    }

    /**
     * @throws AuthorizationDenied
     */
    public static function ensureCanView(StructurePermissions $permissions): void
    {
        if (! $permissions->canView()) {
            throw new AuthorizationDenied('No tienes permiso para ver la estructura.');
        }
    }
}
