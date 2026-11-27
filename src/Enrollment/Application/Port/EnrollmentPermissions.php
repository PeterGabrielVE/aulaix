<?php

declare(strict_types=1);

namespace AulaX\Enrollment\Application\Port;

/**
 * What the signed-in person may do with enrollments in the open
 * institution (S-04, MAT-R11).
 */
interface EnrollmentPermissions
{
    /**
     * gestionar-inscripciones.
     */
    public function canManage(): bool;

    /**
     * aprobar-excepciones-inscripcion: the directive of MAT-R03.4, R04.7 and
     * R14.3.
     */
    public function canApproveExceptions(): bool;

    /**
     * ver-estudiantes.
     */
    public function canView(): bool;

    /**
     * gestionar-estudiantes or gestionar-inscripciones: sees every student
     * of the institution, not only those of their sections (MAT-R08.4).
     */
    public function seesAllStudents(): bool;

    /**
     * The signed-in account, if any.
     */
    public function currentAccountId(): ?int;
}
