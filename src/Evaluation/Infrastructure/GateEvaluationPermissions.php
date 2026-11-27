<?php

declare(strict_types=1);

namespace AulaX\Evaluation\Infrastructure;

use AulaX\Evaluation\Application\Port\EvaluationPermissions;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as Auth;

/**
 * Asks Laravel's gate, which knows the permissions of the signed-in person
 * in the open institution (its permission team).
 */
final readonly class GateEvaluationPermissions implements EvaluationPermissions
{
    /**
     * IdentityAccess's Permission cases; its domain is not reachable from
     * here (A-06).
     */
    private const string MANAGE_GRADES = 'gestionar-calificaciones';

    private const string VIEW_GRADES = 'ver-calificaciones';

    private const string MANAGE_STUDENTS = 'gestionar-estudiantes';

    private const string EDIT_CLOSED_TERM_GRADES = 'editar-notas-lapso-cerrado';

    private const string MANAGE_STRUCTURE = 'gestionar-estructura';

    public function __construct(
        private Gate $gate,
        private Auth $auth,
    ) {}

    public function canGrade(): bool
    {
        return $this->gate->allows(self::MANAGE_GRADES);
    }

    public function canView(): bool
    {
        return $this->gate->allows(self::VIEW_GRADES);
    }

    public function seesAll(): bool
    {
        return $this->gate->allows(self::MANAGE_STUDENTS);
    }

    public function canEditClosedTerms(): bool
    {
        return $this->gate->allows(self::EDIT_CLOSED_TERM_GRADES);
    }

    public function currentAccountId(): ?int
    {
        $id = $this->auth->guard()->id();

        return $id === null ? null : (int) $id;
    }

    public function canAssignPendingTeachers(): bool
    {
        return $this->gate->allows(self::MANAGE_STRUCTURE);
    }
}
