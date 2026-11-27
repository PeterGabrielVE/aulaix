<?php

declare(strict_types=1);

namespace AulaX\Students\Application;

use AulaX\Shared\Domain\AuthorizationDenied;
use AulaX\Students\Application\Port\StudentPermissions;

/**
 * The fine permission check of every use case (S-04, EST-NF01).
 */
final class StudentAccess
{
    /**
     * @throws AuthorizationDenied
     */
    public static function ensureCanManage(StudentPermissions $permissions): void
    {
        if (! $permissions->canManage()) {
            throw new AuthorizationDenied('No tienes permiso para gestionar estudiantes.');
        }
    }

    /**
     * @throws AuthorizationDenied
     */
    public static function ensureCanView(StudentPermissions $permissions): void
    {
        if (! $permissions->canView()) {
            throw new AuthorizationDenied('No tienes permiso para ver estudiantes.');
        }
    }
}
