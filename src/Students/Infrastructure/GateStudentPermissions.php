<?php

declare(strict_types=1);

namespace AulaX\Students\Infrastructure;

use AulaX\Students\Application\Port\StudentPermissions;
use Illuminate\Contracts\Auth\Access\Gate;

/**
 * Asks Laravel's gate, which knows the permissions of the signed-in person
 * in the open institution (its permission team).
 */
final readonly class GateStudentPermissions implements StudentPermissions
{
    /**
     * IdentityAccess's Permission cases; its domain is not reachable from
     * here (A-06).
     */
    private const string MANAGE_STUDENTS = 'gestionar-estudiantes';

    private const string VIEW_STUDENTS = 'ver-estudiantes';

    public function __construct(private Gate $gate) {}

    public function canManage(): bool
    {
        return $this->gate->allows(self::MANAGE_STUDENTS);
    }

    public function canView(): bool
    {
        return $this->gate->allows(self::VIEW_STUDENTS);
    }
}
