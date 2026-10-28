<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application;

use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use AulaX\Shared\Domain\AuthorizationDenied;

/**
 * The fine permission check of every use case (S-04, MAT-NF01).
 */
final class EnrollmentAccess
{
    /**
     * @throws AuthorizationDenied
     */
    public static function ensureCanManage(EnrollmentPermissions $permissions): void
    {
        if (! $permissions->canManage()) {
            throw new AuthorizationDenied('No tienes permiso para gestionar inscripciones.');
        }
    }

    /**
     * @throws AuthorizationDenied
     */
    public static function ensureCanApproveExceptions(EnrollmentPermissions $permissions): void
    {
        if (! $permissions->canApproveExceptions()) {
            throw new AuthorizationDenied('Solo un directivo puede aprobar esta excepción.');
        }
    }

    /**
     * @throws AuthorizationDenied
     */
    public static function ensureCanView(EnrollmentPermissions $permissions): void
    {
        if (! $permissions->canView()) {
            throw new AuthorizationDenied('No tienes permiso para ver estudiantes.');
        }
    }
}
