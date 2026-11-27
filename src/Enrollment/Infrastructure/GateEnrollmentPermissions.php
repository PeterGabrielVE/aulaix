<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Infrastructure;

use AulaX\Enrollment\Application\Port\EnrollmentPermissions;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;

/**
 * Asks Laravel's gate, which knows the permissions of the signed-in person
 * in the open institution (its permission team).
 */
final readonly class GateEnrollmentPermissions implements EnrollmentPermissions
{
    /**
     * IdentityAccess's Permission cases; its domain is not reachable from
     * here (A-06).
     */
    private const string MANAGE_ENROLLMENTS = 'gestionar-inscripciones';

    private const string APPROVE_EXCEPTIONS = 'aprobar-excepciones-inscripcion';

    private const string VIEW_STUDENTS = 'ver-estudiantes';

    private const string MANAGE_STUDENTS = 'gestionar-estudiantes';

    public function __construct(
        private Gate $gate,
        private Auth $auth,
    ) {}

    public function canManage(): bool
    {
        return $this->gate->allows(self::MANAGE_ENROLLMENTS);
    }

    public function canApproveExceptions(): bool
    {
        return $this->gate->allows(self::APPROVE_EXCEPTIONS);
    }

    public function canView(): bool
    {
        return $this->gate->allows(self::VIEW_STUDENTS);
    }

    public function seesAllStudents(): bool
    {
        return $this->gate->any([self::MANAGE_STUDENTS, self::MANAGE_ENROLLMENTS]);
    }

    public function currentAccountId(): ?int
    {
        $id = $this->auth->guard()->id();

        return $id === null ? null : (int) $id;
    }
}
